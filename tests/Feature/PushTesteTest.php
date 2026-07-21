<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Botão "testar avisos": o profissional confirma sozinho que o push
 * chega, sem precisar de um agendamento real.
 */
class PushTesteTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        app()->forgetInstance('current_tenant');

        $this->tenant = Tenant::forceCreate(['slug' => 'pt-'.uniqid(), 'nome' => 'Salão']);
        app()->instance('current_tenant', $this->tenant);
    }

    private function usuario(): User
    {
        return User::forceCreate([
            'name' => 'U', 'email' => 'pt-'.uniqid().'@x.com',
            'password' => Hash::make('secret123'), 'role' => 'admin',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_sem_aparelho_avisa_em_vez_de_fingir_que_enviou(): void
    {
        $this->actingAs($this->usuario(), 'admin');

        $this->postJson(route('admin.push.teste'))
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'motivo' => 'nenhum aparelho ativado']);
    }

    public function test_com_aparelho_reporta_quantos_receberam(): void
    {
        // Sem VAPID o envio é inerte, mas a rota tem que responder direito
        config(['webpush.vapid.public_key' => null, 'webpush.vapid.private_key' => null]);

        $u = $this->usuario();
        PushSubscription::registrar($u, 'https://x/y', str_repeat('a', 87), str_repeat('b', 22));
        $this->actingAs($u, 'admin');

        $this->postJson(route('admin.push.teste'))
            ->assertOk()
            ->assertJson(['ok' => true, 'aparelhos' => 1]);
    }

    public function test_visitante_nao_dispara_teste(): void
    {
        $this->postJson(route('admin.push.teste'))->assertUnauthorized();
    }

    public function test_teste_nao_alcanca_aparelho_de_outro_usuario(): void
    {
        $outro = $this->usuario();
        PushSubscription::registrar($outro, 'https://x/y', str_repeat('a', 87), str_repeat('b', 22));

        // Usuário sem aparelho próprio não pode "testar" no aparelho alheio
        $this->actingAs($this->usuario(), 'admin');

        $this->postJson(route('admin.push.teste'))->assertStatus(422);
    }
}
