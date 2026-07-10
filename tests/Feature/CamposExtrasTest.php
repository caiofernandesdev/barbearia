<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\CampoPersonalizado;
use App\Models\Plano;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Campos personalizados: filtro por resposta (JSON dados_extras) e colunas nos exports.
 */
class CamposExtrasTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Agendamento $comAlergia;

    private Agendamento $semAlergia;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        app()->forgetInstance('current_tenant');

        $plano = Plano::forceCreate([
            'nome' => 'Pro '.uniqid(),
            'preco_mensal' => 197,
            'features' => ['relatorios', 'campos_agendamento'],
            'ativo' => true,
        ]);

        $this->tenant = Tenant::forceCreate([
            'slug' => 'campos-'.uniqid(),
            'nome' => 'Estabelecimento Teste',
            'plano_id' => $plano->id,
        ]);

        $prof = Profissional::forceCreate(['nome' => 'João', 'tenant_id' => $this->tenant->id]);
        $serv = Servico::forceCreate([
            'nome' => 'Corte', 'preco' => 40, 'duracao_minutos' => 30, 'tenant_id' => $this->tenant->id,
        ]);

        CampoPersonalizado::forceCreate([
            'nome' => 'Possui alergia', 'slug' => 'possui_alergia', 'tipo' => 'toggle',
            'obrigatorio' => false, 'ordem' => 1, 'ativo' => true, 'tenant_id' => $this->tenant->id,
        ]);

        $base = [
            'cliente_telefone' => '11987654321',
            'profissional_id' => $prof->id,
            'servico_id' => $serv->id,
            'data_hora' => now()->addDay()->setTime(10, 0),
            'status' => 'confirmado',
            'tenant_id' => $this->tenant->id,
        ];

        $this->comAlergia = Agendamento::forceCreate($base + [
            'cliente_nome' => 'Cliente Alérgico',
            'dados_extras' => ['possui_alergia' => 'Sim'],
        ]);
        $this->semAlergia = Agendamento::forceCreate($base + [
            'cliente_nome' => 'Cliente Tranquilo',
            'cliente_telefone' => '11911112222',
            'data_hora' => now()->addDay()->setTime(11, 0),
            'dados_extras' => ['possui_alergia' => 'Não'],
        ]);
    }

    public function test_consulta_json_por_resposta_do_campo_filtra_corretamente(): void
    {
        // Mesma consulta usada pelo filtro da tabela de agendamentos
        $encontrados = Agendamento::withoutGlobalScopes()
            ->where('dados_extras->possui_alergia', 'Sim')
            ->pluck('cliente_nome');

        $this->assertTrue($encontrados->contains('Cliente Alérgico'));
        $this->assertFalse($encontrados->contains('Cliente Tranquilo'));
    }

    public function test_busca_parcial_em_campo_texto_funciona(): void
    {
        $this->comAlergia->update(['dados_extras' => ['observacao' => 'Alergia a níquel nas máquinas']]);

        $encontrados = Agendamento::withoutGlobalScopes()
            ->where('dados_extras->observacao', 'like', '%níquel%')
            ->count();

        $this->assertSame(1, $encontrados);
    }

    public function test_export_excel_inclui_coluna_e_respostas_do_campo_personalizado(): void
    {
        $admin = User::forceCreate([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@teste.com',
            'password' => Hash::make('secret123'), 'role' => 'admin',
            'tenant_id' => $this->tenant->id,
        ]);

        $inicio = now()->format('Y-m-d');
        $fim = now()->addDays(3)->format('Y-m-d');

        $resp = $this->actingAs($admin, 'admin')
            ->get("/admin-exports/relatorio-excel?inicio={$inicio}&fim={$fim}");

        $resp->assertOk();
        $csv = $resp->streamedContent();

        $this->assertStringContainsString('Possui alergia', $csv); // coluna
        $this->assertStringContainsString('Cliente Alérgico', $csv);
        $this->assertStringContainsString('Sim', $csv);            // resposta
    }
}
