<?php

namespace Tests\Feature;

use App\Filament\Widgets\RepescagemStatsWidget;
use App\Imports\MensalistasImport;
use App\Models\Agendamento;
use App\Models\Mensalista;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Maatwebsite\Excel\Concerns\Importable;
use Tests\TestCase;

/**
 * Import de mensalistas conta importados x duplicados; widget de repescagem
 * conta clientes sumidos por faixa de dias.
 */
class ImportacaoRepescagemTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-08-05 10:00'));
        app()->forgetInstance('current_tenant');

        $this->tenant = Tenant::forceCreate(['slug' => 'p-'.uniqid(), 'nome' => 'Salão']);
        app()->instance('current_tenant', $this->tenant);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ─── Import: contadores ───────────────────────────────────────────────────

    public function test_import_conta_importados_e_duplicados(): void
    {
        // Já existe um mensalista com este telefone → a linha vira "duplicado".
        Mensalista::forceCreate([
            'nome' => 'Já Existe', 'telefone' => '11999999999',
            'tipo' => 'avulso', 'limite_cortes_semana' => 1,
            'valor_mensalidade' => 50, 'tenant_id' => $this->tenant->id,
        ]);

        $import = new class extends MensalistasImport
        {
            use Importable;
        };

        // Simula as linhas da planilha (já com heading row aplicado).
        $import->model(['nome' => 'Novo Cliente', 'telefone' => '(11) 98888-7777', 'tipo' => 'fixo']);
        $import->model(['nome' => 'Outro Novo', 'telefone' => '11977776666']);
        $import->model(['nome' => 'Já Existe', 'telefone' => '11999999999']); // duplicado
        $import->model(['nome' => '', 'telefone' => '']); // ignorado (vazio)

        $this->assertSame(2, $import->importados);
        $this->assertSame(1, $import->duplicados);

        // Persistiu os dois novos, sem duplicar o existente.
        $this->assertSame(3, Mensalista::withoutGlobalScopes()->count());

        // "fixo" na planilha vira o valor válido da enum.
        $novo = Mensalista::where('telefone', '11988887777')->first(); // telefone normalizado
        $this->assertNotNull($novo);
        $this->assertSame('mensalista_fixo', $novo->tipo);
    }

    // ─── Widget de repescagem: faixas ─────────────────────────────────────────

    private function criarAgendamento(string $telefone, int $diasAtras): void
    {
        Agendamento::create([
            'cliente_nome' => 'Cliente '.$telefone,
            'cliente_telefone' => $telefone,
            'profissional_id' => Profissional::firstOrCreate(
                ['nome' => 'Ana', 'tenant_id' => $this->tenant->id]
            )->id,
            'servico_id' => Servico::firstOrCreate(
                ['nome' => 'Corte', 'tenant_id' => $this->tenant->id],
                ['preco' => 40, 'duracao_minutos' => 30]
            )->id,
            'data_hora' => now()->subDays($diasAtras),
            'status' => 'concluido',
            'mensalista' => false,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_widget_conta_clientes_sumidos_por_faixa(): void
    {
        $this->criarAgendamento('11900000001', 40);   // 30+ apenas
        $this->criarAgendamento('11900000002', 100);  // 30+, 60+, 90+
        $this->criarAgendamento('11900000003', 5);    // recente, não conta

        $widget = new RepescagemStatsWidget;

        $this->assertSame(2, $widget->contarAusentes(30));
        $this->assertSame(1, $widget->contarAusentes(60));
        $this->assertSame(1, $widget->contarAusentes(90));
    }

    public function test_widget_ignora_cancelados_e_mensalistas(): void
    {
        $this->criarAgendamento('11900000010', 100);

        // Cancelado não conta como "sumido"
        Agendamento::create([
            'cliente_nome' => 'Cancelado', 'cliente_telefone' => '11900000011',
            'profissional_id' => Profissional::firstOrCreate(['nome' => 'Ana', 'tenant_id' => $this->tenant->id])->id,
            'servico_id' => Servico::firstOrCreate(['nome' => 'Corte', 'tenant_id' => $this->tenant->id], ['preco' => 40, 'duracao_minutos' => 30])->id,
            'data_hora' => now()->subDays(100), 'status' => 'cancelado', 'mensalista' => false,
            'tenant_id' => $this->tenant->id,
        ]);

        $widget = new RepescagemStatsWidget;

        $this->assertSame(1, $widget->contarAusentes(30));
    }

    public function test_widget_renderiza_os_cards(): void
    {
        $this->criarAgendamento('11900000020', 100);

        $admin = User::forceCreate([
            'name' => 'Dono', 'email' => 'r-'.uniqid().'@x.com',
            'password' => Hash::make('secret123'), 'role' => 'admin', 'tenant_id' => $this->tenant->id,
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(RepescagemStatsWidget::class)
            ->assertOk()
            ->assertSee('Clientes sumidos')
            ->assertSee('Há 90+ dias');
    }
}
