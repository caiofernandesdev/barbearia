<?php

namespace Tests\Feature;

use App\Models\Plano;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LimitesPlanoTest extends TestCase
{
    use RefreshDatabase;

    private function tenantComLimite(int $maxProf, int $maxUsers): Tenant
    {
        app()->forgetInstance('current_tenant');
        $plano = Plano::forceCreate([
            'nome' => 'P-'.uniqid(), 'preco_mensal' => 100, 'features' => [],
            'max_profissionais' => $maxProf, 'max_usuarios' => $maxUsers, 'ativo' => true,
        ]);

        return Tenant::forceCreate(['slug' => 'lim-'.uniqid(), 'nome' => 'X', 'plano_id' => $plano->id]);
    }

    public function test_profissionais_bloqueia_ao_atingir_limite(): void
    {
        $tenant = $this->tenantComLimite(maxProf: 2, maxUsers: 0);

        $this->assertTrue($tenant->podeAdicionarProfissional());

        Profissional::forceCreate(['nome' => 'A', 'tenant_id' => $tenant->id]);
        Profissional::forceCreate(['nome' => 'B', 'tenant_id' => $tenant->id]);

        $this->assertFalse($tenant->fresh()->podeAdicionarProfissional());
    }

    public function test_usuarios_bloqueia_ao_atingir_limite(): void
    {
        $tenant = $this->tenantComLimite(maxProf: 0, maxUsers: 1);

        User::forceCreate([
            'name' => 'Dono', 'email' => 'l-'.uniqid().'@x.com',
            'password' => Hash::make('secret123'), 'role' => 'admin', 'tenant_id' => $tenant->id,
        ]);

        $this->assertFalse($tenant->fresh()->podeAdicionarUsuario());
    }

    public function test_limite_zero_e_ilimitado(): void
    {
        $tenant = $this->tenantComLimite(maxProf: 0, maxUsers: 0);

        foreach (range(1, 10) as $i) {
            Profissional::forceCreate(['nome' => "P{$i}", 'tenant_id' => $tenant->id]);
        }

        $this->assertTrue($tenant->fresh()->podeAdicionarProfissional());
        $this->assertTrue($tenant->fresh()->podeAdicionarUsuario());
    }
}
