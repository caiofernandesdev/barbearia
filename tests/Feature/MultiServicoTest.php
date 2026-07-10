<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\ConfiguracaoBarbearia;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\Tenant;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Multi-serviço no agendamento público + serviços por profissional.
 */
class MultiServicoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Profissional $profissional;

    private Servico $corte;

    private Servico $barba;

    private Servico $pigmentacao;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // intercepta EnviarWhatsAppJob do observer
        $this->withoutMiddleware(PreventRequestForgery::class);
        app()->forgetInstance('current_tenant');

        $this->tenant = Tenant::forceCreate([
            'slug' => 'teste-'.uniqid(),
            'nome' => 'Barbearia Teste',
        ]);

        $this->profissional = Profissional::forceCreate([
            'nome' => 'João',
            'tenant_id' => $this->tenant->id,
            'dias_trabalho' => [0, 1, 2, 3, 4, 5, 6],
        ]);

        $this->corte = Servico::forceCreate([
            'nome' => 'Corte', 'preco' => 40.00, 'duracao_minutos' => 30,
            'ordem' => 1, 'tenant_id' => $this->tenant->id,
        ]);
        $this->barba = Servico::forceCreate([
            'nome' => 'Barba', 'preco' => 25.00, 'duracao_minutos' => 60,
            'ordem' => 2, 'tenant_id' => $this->tenant->id,
        ]);
        $this->pigmentacao = Servico::forceCreate([
            'nome' => 'Pigmentação', 'preco' => 80.00, 'duracao_minutos' => 60,
            'ordem' => 3, 'tenant_id' => $this->tenant->id,
        ]);
    }

    private function url(string $path): string
    {
        return "/{$this->tenant->slug}{$path}";
    }

    // ─── Serviços por profissional ────────────────────────────────────────────

    public function test_api_servicos_sem_profissional_retorna_todos_os_ativos(): void
    {
        $resp = $this->getJson($this->url('/api/servicos'));

        $resp->assertOk()->assertJsonCount(3);
    }

    public function test_api_servicos_filtra_pelos_servicos_marcados_do_profissional(): void
    {
        $this->profissional->servicos()->attach([$this->corte->id, $this->barba->id]);

        $resp = $this->getJson($this->url("/api/servicos?profissional_id={$this->profissional->id}"));

        $resp->assertOk()->assertJsonCount(2);
        $nomes = collect($resp->json())->pluck('nome');
        $this->assertTrue($nomes->contains('Corte'));
        $this->assertTrue($nomes->contains('Barba'));
        $this->assertFalse($nomes->contains('Pigmentação'));
    }

    public function test_api_servicos_profissional_sem_marcacao_retorna_todos(): void
    {
        $resp = $this->getJson($this->url("/api/servicos?profissional_id={$this->profissional->id}"));

        $resp->assertOk()->assertJsonCount(3);
    }

    // ─── Agendamento com múltiplos serviços ───────────────────────────────────

    private function payloadAgendamento(array $overrides = []): array
    {
        return array_merge([
            'cliente_nome' => 'Cliente Teste',
            'cliente_telefone' => '11987654321',
            'profissional_id' => $this->profissional->id,
            'servico_id' => $this->corte->id,
            'servico_ids' => $this->corte->id.','.$this->barba->id,
            'data_hora' => now()->addDay()->setTime(10, 0)->format('Y-m-d H:i:s'),
        ], $overrides);
    }

    public function test_agendamento_com_dois_servicos_salva_pivot_e_totais_somados(): void
    {
        $resp = $this->post($this->url('/agendar'), $this->payloadAgendamento());

        $resp->assertRedirect();

        $ag = Agendamento::withoutGlobalScopes()->latest('id')->first();
        $this->assertNotNull($ag);
        $this->assertSame($this->corte->id, $ag->servico_id); // primeiro serviço p/ retrocompat
        $this->assertEquals(65.00, (float) $ag->valor_total); // 40 + 25
        $this->assertSame(90, $ag->duracao_total_minutos);    // 30 + 60
        $this->assertEqualsCanonicalizing(
            [$this->corte->id, $this->barba->id],
            $ag->servicos()->pluck('servicos.id')->all()
        );
        $this->assertSame('Corte + Barba', $ag->nomesServicos());
    }

    public function test_agendamento_com_um_servico_continua_funcionando(): void
    {
        $resp = $this->post($this->url('/agendar'), $this->payloadAgendamento([
            'servico_ids' => (string) $this->barba->id,
            'servico_id' => $this->barba->id,
        ]));

        $resp->assertRedirect();

        $ag = Agendamento::withoutGlobalScopes()->latest('id')->first();
        $this->assertEquals(25.00, (float) $ag->valor_total);
        $this->assertSame(60, $ag->duracao_total_minutos);
    }

    public function test_rejeita_servico_que_o_profissional_nao_realiza(): void
    {
        $this->profissional->servicos()->attach([$this->corte->id]); // só corta

        $resp = $this->post($this->url('/agendar'), $this->payloadAgendamento());

        $resp->assertSessionHasErrors('servico_id');
        $this->assertSame(0, Agendamento::withoutGlobalScopes()->count());
    }

    public function test_rejeita_servico_inexistente_ou_inativo(): void
    {
        $this->pigmentacao->update(['ativo' => false]);

        $resp = $this->post($this->url('/agendar'), $this->payloadAgendamento([
            'servico_ids' => $this->corte->id.','.$this->pigmentacao->id,
        ]));

        $resp->assertSessionHasErrors('servico_id');
        $this->assertSame(0, Agendamento::withoutGlobalScopes()->count());
    }

    // ─── Disponibilidade considera a duração somada ───────────────────────────

    public function test_horarios_disponiveis_diminuem_com_mais_servicos(): void
    {
        $config = ConfiguracaoBarbearia::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $this->tenant->id],
            [
                'nome_barbearia' => 'Teste', 'horario_abertura' => '08:00',
                'horario_encerramento' => '10:00', 'intervalo_minutos' => 60,
                'mensalista_limite_cortes_semana' => 1, 'tenant_id' => $this->tenant->id,
            ]
        );
        $config->update(['horario_abertura' => '08:00', 'horario_encerramento' => '10:00', 'intervalo_minutos' => 60]);

        $data = now()->addDay()->format('Y-m-d');

        // 1 serviço de 30min numa janela 08–10h → 08:00 e 09:00 cabem
        $um = $this->getJson($this->url("/api/horarios-disponiveis?profissional_id={$this->profissional->id}&data={$data}&servico_ids={$this->corte->id}"));
        $um->assertOk();

        // corte + barba = 90min → só 08:00 cabe (08:00 + 90min = 09:30 ≤ 10:00)
        $dois = $this->getJson($this->url("/api/horarios-disponiveis?profissional_id={$this->profissional->id}&data={$data}&servico_ids={$this->corte->id},{$this->barba->id}"));
        $dois->assertOk();

        $this->assertGreaterThan(count($dois->json()), count($um->json()));
        $this->assertSame(2, count($um->json()));
        $this->assertSame(1, count($dois->json()));
    }

    // ─── Retrocompatibilidade: totais preenchidos automaticamente ─────────────

    public function test_agendamento_criado_sem_totais_preenche_a_partir_do_servico(): void
    {
        $ag = Agendamento::create([
            'cliente_nome' => 'Via Admin', 'cliente_telefone' => '11900001111',
            'profissional_id' => $this->profissional->id,
            'servico_id' => $this->barba->id,
            'data_hora' => now()->addDays(2), 'status' => 'pendente',
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertEquals(25.00, (float) $ag->valor_total);
        $this->assertSame(60, $ag->duracao_total_minutos);
    }
}
