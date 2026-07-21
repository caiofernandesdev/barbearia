<?php

namespace Tests\Feature;

use App\Jobs\EnviarWhatsAppJob;
use App\Models\Agendamento;
use App\Models\ConfiguracaoBarbearia;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Avisos no sininho do painel. É o canal que funciona sem WhatsApp:
 * vai para o dono e para o profissional do atendimento.
 */
class AvisoPainelTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Profissional $prof;

    private Servico $servico;

    protected function setUp(): void
    {
        parent::setUp();
        // Finge só o envio de WhatsApp: o aviso do painel também é enfileirado
        // (DatabaseNotification é ShouldQueue) e precisa rodar de verdade aqui
        Queue::fake([EnviarWhatsAppJob::class]);
        Carbon::setTestNow(Carbon::parse('2026-07-15 08:00'));
        app()->forgetInstance('current_tenant');

        $this->tenant = Tenant::forceCreate(['slug' => 'av-'.uniqid(), 'nome' => 'Salão']);
        app()->instance('current_tenant', $this->tenant);

        ConfiguracaoBarbearia::forceCreate([
            'nome_barbearia' => 'Salão', 'horario_abertura' => '08:00',
            'horario_encerramento' => '18:00', 'intervalo_minutos' => 60,
            'mensalista_limite_cortes_semana' => 1, 'tenant_id' => $this->tenant->id,
        ]);

        $this->prof = Profissional::forceCreate(['nome' => 'Ana', 'tenant_id' => $this->tenant->id]);
        $this->servico = Servico::forceCreate([
            'nome' => 'Corte', 'preco' => 40, 'duracao_minutos' => 60,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function usuario(string $role, ?int $profissionalId = null, ?Tenant $tenant = null): User
    {
        return User::forceCreate([
            'name' => 'U', 'email' => 'av-'.uniqid().'@x.com',
            'password' => Hash::make('secret123'), 'role' => $role,
            'profissional_id' => $profissionalId,
            'tenant_id' => ($tenant ?? $this->tenant)->id,
        ]);
    }

    private function agendar(array $attrs = []): Agendamento
    {
        return Agendamento::create(array_merge([
            'cliente_nome' => 'Maria', 'cliente_telefone' => '11987654321',
            'profissional_id' => $this->prof->id, 'servico_id' => $this->servico->id,
            'data_hora' => Carbon::parse('2026-07-16 10:00'),
            'status' => 'pendente', 'tenant_id' => $this->tenant->id,
        ], $attrs));
    }

    private function avisosDe(User $u): int
    {
        return $u->notifications()->count();
    }

    // ─── Quem recebe ──────────────────────────────────────────────────────────

    public function test_dono_e_profissional_recebem_aviso_de_novo_agendamento(): void
    {
        $dono = $this->usuario('admin');
        $barbeiro = $this->usuario('barbeiro', $this->prof->id);

        $this->agendar();

        $this->assertSame(1, $this->avisosDe($dono));
        $this->assertSame(1, $this->avisosDe($barbeiro));
    }

    public function test_aviso_traz_cliente_e_horario(): void
    {
        $dono = $this->usuario('admin');

        $this->agendar();

        $dados = $dono->notifications()->first()->data;
        $texto = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->assertStringContainsString('Novo agendamento', $texto);
        $this->assertStringContainsString('Maria', $texto);
        $this->assertStringContainsString('16/07', $texto);
        $this->assertStringContainsString('10:00', $texto);
    }

    public function test_profissional_de_outro_atendimento_nao_recebe(): void
    {
        $outroProf = Profissional::forceCreate(['nome' => 'Bia', 'tenant_id' => $this->tenant->id]);
        $barbeiroDaBia = $this->usuario('barbeiro', $outroProf->id);

        // Agendamento é da Ana
        $this->agendar();

        $this->assertSame(0, $this->avisosDe($barbeiroDaBia));
    }

    public function test_usuario_de_outro_estabelecimento_nao_recebe(): void
    {
        $outroTenant = Tenant::forceCreate(['slug' => 'x-'.uniqid(), 'nome' => 'Outro']);
        $donoDeFora = $this->usuario('admin', tenant: $outroTenant);

        $this->agendar();

        $this->assertSame(0, $this->avisosDe($donoDeFora), 'vazamento entre tenants');
    }

    public function test_quem_criou_nao_recebe_aviso_do_proprio_ato(): void
    {
        $dono = $this->usuario('admin');
        $outroDono = $this->usuario('admin');
        $this->actingAs($dono, 'admin');

        $this->agendar();

        $this->assertSame(0, $this->avisosDe($dono), 'ele mesmo marcou, já sabe');
        $this->assertSame(1, $this->avisosDe($outroDono));
    }

    // ─── Outros eventos ───────────────────────────────────────────────────────

    public function test_cancelamento_gera_aviso(): void
    {
        $dono = $this->usuario('admin');
        $ag = $this->agendar(['status' => 'confirmado']);
        $dono->notifications()->delete();

        $ag->update(['status' => 'cancelado']);

        $this->assertSame(1, $this->avisosDe($dono));
        $this->assertStringContainsString(
            'cancelado',
            json_encode($dono->notifications()->first()->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    public function test_remarcacao_gera_aviso(): void
    {
        $dono = $this->usuario('admin');
        $ag = $this->agendar();
        $dono->notifications()->delete();

        $ag->update(['data_hora' => Carbon::parse('2026-07-17 15:00')]);

        $this->assertSame(1, $this->avisosDe($dono));
        $texto = json_encode($dono->notifications()->first()->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertStringContainsString('remarcado', $texto);
        $this->assertStringContainsString('17/07', $texto);
    }

    public function test_confirmacao_nao_gera_aviso(): void
    {
        $dono = $this->usuario('admin');
        $ag = $this->agendar();
        $dono->notifications()->delete();

        // Cliente confirmando não precisa virar aviso no painel — vira ruído
        $ag->update(['status' => 'confirmado']);

        $this->assertSame(0, $this->avisosDe($dono));
    }

    // ─── Funciona sem WhatsApp ────────────────────────────────────────────────

    public function test_aviso_chega_mesmo_sem_profissional_com_login(): void
    {
        // Nenhum profissional tem conta — o dono ainda tem que ser avisado
        $dono = $this->usuario('admin');

        $this->agendar();

        $this->assertSame(1, $this->avisosDe($dono));
    }
}
