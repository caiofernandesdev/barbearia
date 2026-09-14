<?php

namespace Tests\Feature;

use App\Livewire\Admin\AgendaDiaTable;
use App\Models\ConfiguracaoBarbearia;
use App\Models\Indisponibilidade;
use App\Models\Plano;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Indisponibilidade pela agenda, dias de antecedência configuráveis (público)
 * e dias anteriores no carrossel do painel.
 */
class AgendaIndispEDiasTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Profissional $prof;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-08-18 09:00'));
        $this->withoutMiddleware(PreventRequestForgery::class);
        app()->forgetInstance('current_tenant');

        $plano = Plano::forceCreate(['nome' => 'P-'.uniqid(), 'preco_mensal' => 100, 'features' => ['indisponibilidades'], 'ativo' => true]);
        $this->tenant = Tenant::forceCreate(['slug' => 'ag-'.uniqid(), 'nome' => 'Salão', 'plano_id' => $plano->id]);
        app()->instance('current_tenant', $this->tenant);

        ConfiguracaoBarbearia::forceCreate([
            'nome_barbearia' => 'Salão', 'horario_abertura' => '08:00', 'horario_encerramento' => '19:00',
            'intervalo_minutos' => 30, 'mensalista_limite_cortes_semana' => 1,
            'dias_antecedencia_agendamento' => 14, 'tenant_id' => $this->tenant->id,
        ]);
        $this->prof = Profissional::forceCreate(['nome' => 'Ana', 'ativo' => true, 'tenant_id' => $this->tenant->id, 'dias_trabalho' => [0, 1, 2, 3, 4, 5, 6]]);
        Servico::forceCreate(['nome' => 'Corte', 'preco' => 40, 'duracao_minutos' => 30, 'ativo' => true, 'tenant_id' => $this->tenant->id]);
        $this->admin = User::forceCreate([
            'name' => 'Dono', 'email' => 'ag-'.uniqid().'@x.com',
            'password' => Hash::make('secret123'), 'role' => 'admin', 'tenant_id' => $this->tenant->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ─── Feature A: Indisponibilidade pela agenda ─────────────────────────────

    public function test_cria_indisponibilidade_pela_agenda_no_dia_selecionado(): void
    {
        Livewire::actingAs($this->admin, 'admin')
            ->test(AgendaDiaTable::class, ['profissionalId' => $this->prof->id])
            ->set('dataSelecionada', '2026-08-20')
            ->mountAction('indisponibilidade')
            ->setActionData([
                'profissional_id' => $this->prof->id,
                'hora_inicio' => '12:00',
                'hora_fim' => '13:00',
                'motivo' => 'Almoço',
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $ind = Indisponibilidade::withoutGlobalScopes()->latest('id')->first();
        $this->assertNotNull($ind);
        $this->assertSame('2026-08-20 12:00:00', $ind->inicio->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-20 13:00:00', $ind->fim->format('Y-m-d H:i:s'));
        $this->assertSame($this->prof->id, $ind->profissional_id);
        $this->assertSame('Almoço', $ind->motivo);
    }

    public function test_indisponibilidade_recusa_fim_antes_do_inicio(): void
    {
        Livewire::actingAs($this->admin, 'admin')
            ->test(AgendaDiaTable::class, ['profissionalId' => $this->prof->id])
            ->set('dataSelecionada', '2026-08-20')
            ->mountAction('indisponibilidade')
            ->setActionData(['hora_inicio' => '13:00', 'hora_fim' => '12:00'])
            ->callMountedAction();

        $this->assertSame(0, Indisponibilidade::withoutGlobalScopes()->count());
    }

    // ─── Feature C: dias anteriores no carrossel ──────────────────────────────

    public function test_carrossel_inclui_dias_anteriores_e_futuros(): void
    {
        $comp = new AgendaDiaTable;
        $comp->profissionalId = $this->prof->id;
        $comp->dataSelecionada = '2026-08-18';

        $datas = collect($comp->getDias())->pluck('data');

        $this->assertTrue($datas->contains('2026-08-11'));  // 7 dias antes
        $this->assertTrue($datas->contains('2026-08-18'));  // hoje
        $this->assertTrue($datas->contains('2026-09-01'));  // ~2 semanas à frente

        // Dias anteriores marcados como passado
        $onze = collect($comp->getDias())->firstWhere('data', '2026-08-11');
        $this->assertTrue($onze['passado']);
    }

    // ─── Feature B: janela de agendamento configurável (público) ──────────────

    public function test_api_respeita_dias_de_antecedencia_configurados(): void
    {
        ConfiguracaoBarbearia::getInstance()->update(['dias_antecedencia_agendamento' => 5]);
        $servico = Servico::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->first();

        $base = "/{$this->tenant->slug}/api/horarios-disponiveis?profissional_id={$this->prof->id}&servico_id={$servico->id}";

        // Dentro da janela (3 dias) → OK
        $dentro = Carbon::today()->addDays(3)->format('Y-m-d');
        $this->getJson("{$base}&data={$dentro}")->assertOk();

        // Fora da janela (10 dias, > 5) → recusa
        $fora = Carbon::today()->addDays(10)->format('Y-m-d');
        $this->getJson("{$base}&data={$fora}")->assertStatus(422);
    }
}
