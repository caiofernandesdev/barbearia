<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PodeCancelarTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, bool $podeCancelar): User
    {
        app()->forgetInstance('current_tenant');
        $tenant = Tenant::forceCreate(['slug' => 'pc-'.uniqid(), 'nome' => 'Salão']);

        return User::forceCreate([
            'name' => 'U', 'email' => 'pc-'.uniqid().'@x.com',
            'password' => Hash::make('secret123'), 'role' => $role,
            'pode_cancelar' => $podeCancelar, 'tenant_id' => $tenant->id,
        ]);
    }

    public function test_admin_sempre_pode_cancelar(): void
    {
        $this->assertTrue($this->user('admin', false)->podeCancelar());
    }

    public function test_profissional_com_permissao_pode(): void
    {
        $this->assertTrue($this->user('barbeiro', true)->podeCancelar());
    }

    public function test_profissional_sem_permissao_nao_pode(): void
    {
        $this->assertFalse($this->user('barbeiro', false)->podeCancelar());
    }
}
