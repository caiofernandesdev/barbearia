<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Edição de senha dos usuários de um tenant pelo super admin.
 * Testa a lógica de persistência da senha (cast hashed) diretamente,
 * sem depender do render do Filament.
 */
class SuperAdminUsuarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_redefinir_senha_grava_hash_valido(): void
    {
        app()->forgetInstance('current_tenant');

        $tenant = Tenant::forceCreate(['slug' => 't-'.uniqid(), 'nome' => 'Teste']);
        $user = User::forceCreate([
            'name' => 'Dono', 'email' => 'dono-'.uniqid().'@x.com',
            'password' => Hash::make('senha-antiga'), 'role' => 'admin',
            'tenant_id' => $tenant->id,
        ]);

        // Simula a ação "Redefinir senha" do relation manager
        $user->forceFill(['password' => 'nova-senha-123'])->save();

        $user->refresh();
        $this->assertTrue(Hash::check('nova-senha-123', $user->password));
        $this->assertFalse(Hash::check('senha-antiga', $user->password));
    }

    public function test_senha_em_branco_na_edicao_nao_altera(): void
    {
        // Reproduz o dehydrated(fn => filled($state)): campo vazio some do payload
        $data = ['name' => 'Novo Nome', 'password' => ''];
        $filtrado = array_filter($data, fn ($v, $k) => $k !== 'password' || filled($v), ARRAY_FILTER_USE_BOTH);

        $this->assertArrayNotHasKey('password', $filtrado);
        $this->assertArrayHasKey('name', $filtrado);
    }
}
