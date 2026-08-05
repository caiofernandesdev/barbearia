<?php

namespace Tests\Feature;

use App\Filament\Pages\SalarioEmocional;
use App\Filament\Resources\Agendamentos\AgendamentoResource;
use App\Filament\Resources\Usuarios\UsuarioResource;
use App\Models\Plano;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Permissões de acesso por usuário. NULL = comportamento por papel
 * (retrocompat); array = exatamente o que está marcado.
 */
class PermissoesUsuarioTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        app()->forgetInstance('current_tenant');
        $plano = Plano::forceCreate([
            'nome' => 'P-'.uniqid(), 'slug' => 's-'.uniqid(), 'preco_mensal' => 100,
            'features' => ['salario_emocional'], 'ativo' => true,
        ]);
        $this->tenant = Tenant::forceCreate(['slug' => 'perm-'.uniqid(), 'nome' => 'Salão', 'plano_id' => $plano->id]);
        app()->instance('current_tenant', $this->tenant);
    }

    private function user(string $role, ?array $permissoes = null): User
    {
        return User::forceCreate([
            'name' => 'U', 'email' => 'perm-'.uniqid().'@x.com',
            'password' => Hash::make('secret123'), 'role' => $role,
            'permissoes' => $permissoes, 'tenant_id' => $this->tenant->id,
        ]);
    }

    // ─── temPermissao ─────────────────────────────────────────────────────────

    public function test_admin_sem_config_ve_tudo(): void
    {
        $admin = $this->user('admin');
        foreach (array_keys(User::PERMISSOES) as $slug) {
            $this->assertTrue($admin->temPermissao($slug), "admin deveria ver {$slug}");
        }
    }

    public function test_barbeiro_sem_config_ve_so_o_padrao(): void
    {
        $barbeiro = $this->user('barbeiro');
        $this->assertTrue($barbeiro->temPermissao('agenda_fixa'));
        $this->assertTrue($barbeiro->temPermissao('salario_emocional'));
        $this->assertFalse($barbeiro->temPermissao('agendamentos'));
        $this->assertFalse($barbeiro->temPermissao('configuracoes'));
    }

    public function test_permissoes_explicitas_valem_exatamente(): void
    {
        $u = $this->user('barbeiro', ['agendamentos', 'clientes']);
        $this->assertTrue($u->temPermissao('agendamentos'));
        $this->assertTrue($u->temPermissao('clientes'));
        $this->assertFalse($u->temPermissao('salario_emocional'), 'não marcou, não vê');
        $this->assertFalse($u->temPermissao('relatorios'));
    }

    public function test_admin_restrito_perde_acesso_mas_nunca_usuarios(): void
    {
        // Admin secundário com permissões limitadas (sem 'usuarios' marcado)
        $u = $this->user('admin', ['agenda', 'agendamentos']);
        $this->assertTrue($u->temPermissao('agenda'));
        $this->assertFalse($u->temPermissao('relatorios'), 'restrito de fato');
        // Salvaguarda: admin nunca se tranca fora de Usuários
        $this->assertTrue($u->temPermissao('usuarios'));
    }

    public function test_super_admin_ve_tudo(): void
    {
        $s = $this->user('super_admin', ['nada']);
        $this->assertTrue($s->temPermissao('agendamentos'));
        $this->assertTrue($s->temPermissao('qualquer_coisa'));
    }

    // ─── canAccess (integração) ───────────────────────────────────────────────

    public function test_barbeiro_com_permissao_acessa_agendamentos(): void
    {
        $this->actingAs($this->user('barbeiro', ['agendamentos']), 'admin');
        $this->assertTrue(AgendamentoResource::canAccess());
    }

    public function test_barbeiro_sem_permissao_nao_acessa_agendamentos(): void
    {
        $this->actingAs($this->user('barbeiro', []), 'admin');
        $this->assertFalse(AgendamentoResource::canAccess());
    }

    public function test_admin_restrito_nao_acessa_agendamentos_mas_acessa_usuarios(): void
    {
        $this->actingAs($this->user('admin', ['agenda']), 'admin');
        $this->assertFalse(AgendamentoResource::canAccess());
        $this->assertTrue(UsuarioResource::canAccess());
    }

    public function test_permissao_respeita_feature_do_plano(): void
    {
        // Tem a permissão, mas o plano NÃO tem a feature → não acessa
        $planoSemSE = Plano::forceCreate([
            'nome' => 'Basico-'.uniqid(), 'slug' => 'b-'.uniqid(), 'preco_mensal' => 50,
            'features' => [], 'ativo' => true,
        ]);
        $this->tenant->update(['plano_id' => $planoSemSE->id]);
        app()->instance('current_tenant', $this->tenant->fresh());

        $this->actingAs($this->user('barbeiro', ['salario_emocional']), 'admin');
        $this->assertFalse(SalarioEmocional::canAccess(), 'sem a feature do plano, permissão não basta');
    }
}
