<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\Tenant;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Preço por dia da semana no serviço: dia configurado cobra diferente,
 * dia sem valor usa o preço base. O valor_total do agendamento reflete
 * o dia do próprio atendimento.
 */
class PrecoPorDiaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Profissional $profissional;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
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
    }

    private function url(string $path): string
    {
        return "/{$this->tenant->slug}{$path}";
    }

    // ─── Model ────────────────────────────────────────────────────────────────

    public function test_preco_no_dia_usa_valor_configurado_do_dia(): void
    {
        $servico = Servico::forceCreate([
            'nome' => 'Corte', 'preco' => 40.00, 'duracao_minutos' => 30,
            'ordem' => 1, 'tenant_id' => $this->tenant->id,
            'precos_por_dia' => [6 => 60.00], // sábado mais caro
        ]);

        $this->assertSame(60.0, $servico->precoNoDia(6));  // sábado
        $this->assertSame(40.0, $servico->precoNoDia(3));  // quarta usa base
        $this->assertTrue($servico->temPrecoVariavel());
    }

    public function test_preco_no_dia_sem_config_usa_base(): void
    {
        $servico = Servico::forceCreate([
            'nome' => 'Corte', 'preco' => 40.00, 'duracao_minutos' => 30,
            'ordem' => 1, 'tenant_id' => $this->tenant->id,
        ]);

        $this->assertSame(40.0, $servico->precoNoDia(6));
        $this->assertFalse($servico->temPrecoVariavel());
    }

    public function test_precos_por_dia_aceita_chave_string(): void
    {
        // JSON do banco pode voltar com chaves string ("6" em vez de 6).
        $servico = Servico::forceCreate([
            'nome' => 'Corte', 'preco' => 40.00, 'duracao_minutos' => 30,
            'ordem' => 1, 'tenant_id' => $this->tenant->id,
            'precos_por_dia' => ['6' => 60.00],
        ]);

        $this->assertSame(60.0, $servico->precoNoDia(6));
    }

    // ─── Agendamento público ──────────────────────────────────────────────────

    public function test_valor_total_do_agendamento_reflete_o_dia(): void
    {
        $servico = Servico::forceCreate([
            'nome' => 'Corte', 'preco' => 40.00, 'duracao_minutos' => 30,
            'ordem' => 1, 'tenant_id' => $this->tenant->id,
            'precos_por_dia' => [6 => 60.00], // sábado (Carbon dayOfWeek: 6 = sábado)
        ]);

        // Próximo sábado às 10h
        $sabado = Carbon::now()->next(Carbon::SATURDAY)->setTime(10, 0);

        $resp = $this->post($this->url('/agendar'), [
            'cliente_nome' => 'Cliente Teste',
            'cliente_telefone' => '11987654321',
            'profissional_id' => $this->profissional->id,
            'servico_id' => $servico->id,
            'servico_ids' => (string) $servico->id,
            'data_hora' => $sabado->format('Y-m-d H:i:s'),
        ]);

        $resp->assertRedirect();

        $ag = Agendamento::withoutGlobalScopes()->latest('id')->first();
        $this->assertEquals(60.00, (float) $ag->valor_total);
    }

    public function test_valor_total_usa_base_em_dia_sem_preco_especial(): void
    {
        $servico = Servico::forceCreate([
            'nome' => 'Corte', 'preco' => 40.00, 'duracao_minutos' => 30,
            'ordem' => 1, 'tenant_id' => $this->tenant->id,
            'precos_por_dia' => [6 => 60.00],
        ]);

        // Próxima quarta (sem preço especial) → base
        $quarta = Carbon::now()->next(Carbon::WEDNESDAY)->setTime(10, 0);

        $resp = $this->post($this->url('/agendar'), [
            'cliente_nome' => 'Cliente Teste',
            'cliente_telefone' => '11987654322',
            'profissional_id' => $this->profissional->id,
            'servico_id' => $servico->id,
            'servico_ids' => (string) $servico->id,
            'data_hora' => $quarta->format('Y-m-d H:i:s'),
        ]);

        $resp->assertRedirect();

        $ag = Agendamento::withoutGlobalScopes()->latest('id')->first();
        $this->assertEquals(40.00, (float) $ag->valor_total);
    }

    public function test_api_servicos_expoe_precos_por_dia(): void
    {
        Servico::forceCreate([
            'nome' => 'Corte', 'preco' => 40.00, 'duracao_minutos' => 30,
            'ordem' => 1, 'tenant_id' => $this->tenant->id,
            'precos_por_dia' => [6 => 60.00],
        ]);

        $resp = $this->getJson($this->url('/api/servicos'));

        $resp->assertOk();
        $servico = collect($resp->json())->firstWhere('nome', 'Corte');
        $this->assertNotNull($servico['precos_por_dia']);
        $this->assertEquals(60.00, $servico['precos_por_dia'][6] ?? $servico['precos_por_dia']['6']);
    }
}
