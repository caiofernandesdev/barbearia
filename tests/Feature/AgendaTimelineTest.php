<?php

namespace Tests\Feature;

use App\Livewire\Admin\AgendaDiaTable;
use App\Models\Agendamento;
use App\Models\ConfiguracaoBarbearia;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Vista de agenda (timeline) na agenda do painel: alterna com os slots e
 * posiciona cada agendamento por horário/duração.
 */
class AgendaTimelineTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Profissional $prof;

    private Servico $servico;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-08-18 09:09')); // igual ao print
        app()->forgetInstance('current_tenant');

        $this->tenant = Tenant::forceCreate(['slug' => 'tl-'.uniqid(), 'nome' => 'Salão']);
        app()->instance('current_tenant', $this->tenant);
        ConfiguracaoBarbearia::forceCreate([
            'nome_barbearia' => 'Salão', 'horario_abertura' => '08:00', 'horario_encerramento' => '19:00',
            'intervalo_minutos' => 30, 'mensalista_limite_cortes_semana' => 1, 'tenant_id' => $this->tenant->id,
        ]);
        $this->prof = Profissional::forceCreate(['nome' => 'Ana', 'tenant_id' => $this->tenant->id, 'dias_trabalho' => [0, 1, 2, 3, 4, 5, 6]]);
        $this->servico = Servico::forceCreate(['nome' => 'Corte de cabelo', 'preco' => 40, 'duracao_minutos' => 40, 'tenant_id' => $this->tenant->id]);
        $this->admin = User::forceCreate([
            'name' => 'Dono', 'email' => 'tl-'.uniqid().'@x.com',
            'password' => Hash::make('secret123'), 'role' => 'admin', 'tenant_id' => $this->tenant->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function agendamento(string $hora, string $cliente): Agendamento
    {
        return Agendamento::create([
            'cliente_nome' => $cliente, 'cliente_telefone' => '1190000'.rand(1000, 9999),
            'profissional_id' => $this->prof->id, 'servico_id' => $this->servico->id,
            'valor_total' => 40, 'duracao_total_minutos' => 40,
            'data_hora' => '2026-08-18 '.$hora.':00', 'status' => 'confirmado',
            'mensalista' => false, 'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_alterna_entre_slots_e_agenda_e_mostra_o_agendamento(): void
    {
        $this->agendamento('11:00', 'Carlos Eduardo');

        Livewire::actingAs($this->admin, 'admin')
            ->test(AgendaDiaTable::class, ['profissionalId' => $this->prof->id])
            ->set('dataSelecionada', '2026-08-18')
            ->assertSet('modoAgenda', 'slots')
            ->set('modoAgenda', 'agenda')
            ->assertOk()
            ->assertSee('Carlos Eduardo')
            ->assertSee('11:00 - 11:40'); // faixa de horário do bloco
    }

    public function test_timeline_posiciona_bloco_por_horario_e_duracao(): void
    {
        $this->agendamento('11:00', 'Carlos');

        $comp = new AgendaDiaTable;
        $comp->profissionalId = $this->prof->id;
        $comp->dataSelecionada = '2026-08-18';

        $tl = $comp->getTimeline();

        $this->assertCount(1, $tl['blocos']);
        $bloco = $tl['blocos'][0];

        // Janela começa 08:00; 11:00 = 180min depois; pxPorMin 2.2 → 396px
        $this->assertEqualsWithDelta(396.0, $bloco['top'], 0.5);
        $this->assertEqualsWithDelta(88.0, $bloco['height'], 0.5); // 40min * 2.2
        $this->assertSame('11:00', $bloco['inicio']);
        $this->assertSame('11:40', $bloco['fim']);

        // Linha do agora (hoje, 09:09 = 69min após 08:00 → 96.6px)
        $this->assertNotNull($tl['agoraTop']);
        $this->assertSame('09:09', $tl['agoraLabel']);

        // Listras intermediárias a cada intervalo (30min aqui): 08:30, 09:30, ...
        $this->assertNotEmpty($tl['subLinhas']);
    }

    public function test_listras_seguem_o_intervalo_de_agendamento(): void
    {
        ConfiguracaoBarbearia::getInstance()->update(['intervalo_minutos' => 10]);

        $comp = new AgendaDiaTable;
        $comp->profissionalId = $this->prof->id;
        $comp->dataSelecionada = '2026-08-18';

        $tl = $comp->getTimeline();

        // 10 em 10 min → 5 listras por hora (10,20,30,40,50), fora as horas cheias.
        // Primeira listra: 08:10 = 10min * 2.2 = 22px
        $this->assertContains(22.0, $tl['subLinhas']);
        $this->assertContains(44.0, $tl['subLinhas']); // 08:20
    }

    public function test_agenda_de_outro_dia_nao_tem_linha_do_agora(): void
    {
        $comp = new AgendaDiaTable;
        $comp->profissionalId = $this->prof->id;
        $comp->dataSelecionada = '2026-08-20'; // não é hoje

        $this->assertNull($comp->getTimeline()['agoraTop']);
    }
}
