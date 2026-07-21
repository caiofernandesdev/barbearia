<?php

namespace Tests\Feature;

use App\Jobs\EnviarPushJob;
use App\Jobs\EnviarWhatsAppJob;
use App\Models\Agendamento;
use App\Models\ConfiguracaoBarbearia;
use App\Models\Profissional;
use App\Models\PushSubscription;
use App\Models\Servico;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PushService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Notificação push do painel: registro do aparelho e disparo junto
 * com o aviso do sininho.
 */
class PushTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Profissional $prof;

    private Servico $servico;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake([EnviarWhatsAppJob::class, EnviarPushJob::class]);
        Carbon::setTestNow(Carbon::parse('2026-07-15 08:00'));
        $this->withoutMiddleware(PreventRequestForgery::class);
        app()->forgetInstance('current_tenant');

        $this->tenant = Tenant::forceCreate(['slug' => 'push-'.uniqid(), 'nome' => 'Salão']);
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

    private function usuario(string $role = 'admin', ?int $profissionalId = null): User
    {
        return User::forceCreate([
            'name' => 'U', 'email' => 'push-'.uniqid().'@x.com',
            'password' => Hash::make('secret123'), 'role' => $role,
            'profissional_id' => $profissionalId,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    private function payload(array $o = []): array
    {
        return array_merge([
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.uniqid(),
            'keys' => ['p256dh' => str_repeat('a', 87), 'auth' => str_repeat('b', 22)],
        ], $o);
    }

    // ─── Registro do aparelho ─────────────────────────────────────────────────

    public function test_usuario_logado_registra_o_aparelho(): void
    {
        $u = $this->usuario();
        $this->actingAs($u, 'admin');

        $this->postJson(route('admin.push.inscrever'), $this->payload())
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertSame($u->id, PushSubscription::first()->user_id);
    }

    public function test_visitante_nao_registra_aparelho(): void
    {
        $this->postJson(route('admin.push.inscrever'), $this->payload())
            ->assertUnauthorized();

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_mesmo_aparelho_nao_duplica(): void
    {
        $u = $this->usuario();
        $this->actingAs($u, 'admin');
        $payload = $this->payload();

        $this->postJson(route('admin.push.inscrever'), $payload)->assertOk();
        $this->postJson(route('admin.push.inscrever'), $payload)->assertOk();

        $this->assertDatabaseCount('push_subscriptions', 1);
    }

    public function test_usuario_pode_ter_varios_aparelhos(): void
    {
        $u = $this->usuario();
        $this->actingAs($u, 'admin');

        $this->postJson(route('admin.push.inscrever'), $this->payload())->assertOk();
        $this->postJson(route('admin.push.inscrever'), $this->payload())->assertOk();

        $this->assertDatabaseCount('push_subscriptions', 2);
    }

    public function test_desinscrever_remove_so_o_proprio_aparelho(): void
    {
        $dono = $this->usuario();
        $outro = $this->usuario();
        $payloadDoOutro = $this->payload();

        $this->actingAs($outro, 'admin');
        $this->postJson(route('admin.push.inscrever'), $payloadDoOutro)->assertOk();

        // Dono tenta remover o aparelho do outro
        $this->actingAs($dono, 'admin');
        $this->postJson(route('admin.push.desinscrever'), ['endpoint' => $payloadDoOutro['endpoint']])
            ->assertOk();

        // Continua 1: não podia ter removido o aparelho alheio
        $this->assertDatabaseCount('push_subscriptions', 1);
    }

    // ─── Disparo ──────────────────────────────────────────────────────────────

    public function test_novo_agendamento_enfileira_push_para_o_dono(): void
    {
        $dono = $this->usuario();

        Agendamento::create([
            'cliente_nome' => 'Maria', 'cliente_telefone' => '11987654321',
            'profissional_id' => $this->prof->id, 'servico_id' => $this->servico->id,
            'data_hora' => Carbon::parse('2026-07-16 10:00'),
            'status' => 'pendente', 'tenant_id' => $this->tenant->id,
        ]);

        Queue::assertPushed(EnviarPushJob::class, function ($job) use ($dono) {
            return $job->userId === $dono->id
                && $job->payload['title'] === 'Novo agendamento'
                && str_contains($job->payload['body'], 'Maria');
        });
    }

    // ─── Segurança / configuração ─────────────────────────────────────────────

    public function test_sem_chaves_vapid_o_servico_fica_inativo(): void
    {
        config(['webpush.vapid.public_key' => null, 'webpush.vapid.private_key' => null]);

        $this->assertFalse(app(PushService::class)->configurado());
    }

    public function test_envio_sem_configuracao_nao_quebra(): void
    {
        config(['webpush.vapid.public_key' => null, 'webpush.vapid.private_key' => null]);
        $u = $this->usuario();
        PushSubscription::registrar($u, 'https://x/y', str_repeat('a', 87), str_repeat('b', 22));

        // Não pode lançar exceção — push é acessório, não pode derrubar nada
        app(PushService::class)->enviarPara($u, ['title' => 't', 'body' => 'b']);

        $this->assertTrue(true);
    }

    public function test_usuario_sem_aparelho_nao_quebra(): void
    {
        $u = $this->usuario();

        app(PushService::class)->enviarPara($u, ['title' => 't', 'body' => 'b']);

        $this->assertTrue(true);
    }
}
