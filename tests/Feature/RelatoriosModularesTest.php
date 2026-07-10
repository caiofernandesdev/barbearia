<?php

namespace Tests\Feature;

use App\Filament\SuperAdmin\Resources\Planos\PlanosResource;
use App\Models\Plano;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Relatórios granulares por plano (features rel_*).
 */
class RelatoriosModularesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        app()->forgetInstance('current_tenant');
    }

    private function tenantComFeatures(array $features): Tenant
    {
        $plano = Plano::forceCreate([
            'nome' => 'Plano '.uniqid(),
            'preco_mensal' => 100,
            'features' => $features,
            'ativo' => true,
        ]);

        return Tenant::forceCreate([
            'slug' => 'rel-'.uniqid(),
            'nome' => 'Teste',
            'plano_id' => $plano->id,
        ]);
    }

    // ─── Tenant::hasRelatorio ────────────────────────────────────────────────

    public function test_sem_modulo_relatorios_nenhum_relatorio_liberado(): void
    {
        $tenant = $this->tenantComFeatures(['mensalistas']);

        $this->assertFalse($tenant->hasRelatorio('receita'));
        $this->assertFalse($tenant->hasRelatorio('agendamentos_periodo'));
    }

    public function test_plano_legado_sem_granularidade_libera_todos(): void
    {
        $tenant = $this->tenantComFeatures(['relatorios']);

        foreach (array_keys(Plano::RELATORIOS) as $slug) {
            $this->assertTrue($tenant->hasRelatorio(substr($slug, 4)), "esperava {$slug} liberado");
        }
    }

    public function test_plano_granular_libera_somente_os_marcados(): void
    {
        $tenant = $this->tenantComFeatures(['relatorios', 'rel_receita', 'rel_agendamentos_periodo']);

        $this->assertTrue($tenant->hasRelatorio('receita'));
        $this->assertTrue($tenant->hasRelatorio('agendamentos_periodo'));
        $this->assertFalse($tenant->hasRelatorio('desempenho_barbeiro'));
        $this->assertFalse($tenant->hasRelatorio('evolucao_mensal'));
        $this->assertFalse($tenant->hasRelatorio('cancelamentos'));
    }

    // ─── Merge do form do super admin ────────────────────────────────────────

    public function test_merge_junta_relatorios_marcados_nas_features(): void
    {
        $data = PlanosResource::mesclarRelatoriosNasFeatures([
            'features' => ['relatorios', 'whatsapp'],
            'relatorios_inclusos' => ['rel_receita', 'rel_atendimentos'],
        ]);

        $this->assertEqualsCanonicalizing(
            ['relatorios', 'whatsapp', 'rel_receita', 'rel_atendimentos'],
            $data['features']
        );
        $this->assertArrayNotHasKey('relatorios_inclusos', $data);
    }

    public function test_merge_descarta_relatorios_se_modulo_relatorios_desmarcado(): void
    {
        $data = PlanosResource::mesclarRelatoriosNasFeatures([
            'features' => ['whatsapp'],
            'relatorios_inclusos' => ['rel_receita'],
        ]);

        $this->assertSame(['whatsapp'], $data['features']);
    }

    // ─── Export respeita os módulos ──────────────────────────────────────────

    public function test_export_excel_omite_secoes_fora_do_plano(): void
    {
        $tenant = $this->tenantComFeatures(['relatorios', 'rel_agendamentos_periodo']);

        $admin = User::forceCreate([
            'name' => 'Admin', 'email' => 'rel-'.uniqid().'@teste.com',
            'password' => Hash::make('secret123'), 'role' => 'admin',
            'tenant_id' => $tenant->id,
        ]);

        $inicio = now()->format('Y-m-d');
        $fim = now()->addDays(3)->format('Y-m-d');

        $resp = $this->actingAs($admin, 'admin')
            ->get("/admin-exports/relatorio-excel?inicio={$inicio}&fim={$fim}");

        $resp->assertOk();
        $csv = $resp->streamedContent();

        $this->assertStringContainsString('LISTA DE AGENDAMENTOS DO PERÍODO', $csv);
        $this->assertStringNotContainsString('DESEMPENHO POR BARBEIRO', $csv);
        $this->assertStringNotContainsString('EVOLUÇÃO MENSAL', $csv);
        $this->assertStringNotContainsString('Receita Total', $csv);
    }

    public function test_export_excel_plano_legado_traz_todas_as_secoes(): void
    {
        $tenant = $this->tenantComFeatures(['relatorios']);

        $admin = User::forceCreate([
            'name' => 'Admin', 'email' => 'rel-'.uniqid().'@teste.com',
            'password' => Hash::make('secret123'), 'role' => 'admin',
            'tenant_id' => $tenant->id,
        ]);

        $resp = $this->actingAs($admin, 'admin')->get('/admin-exports/relatorio-excel');

        $resp->assertOk();
        $csv = $resp->streamedContent();

        $this->assertStringContainsString('DESEMPENHO POR BARBEIRO', $csv);
        $this->assertStringContainsString('EVOLUÇÃO MENSAL', $csv);
        $this->assertStringContainsString('LISTA DE AGENDAMENTOS DO PERÍODO', $csv);
    }
}
