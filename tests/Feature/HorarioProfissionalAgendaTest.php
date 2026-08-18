<?php

namespace Tests\Feature;

use App\Livewire\Admin\AgendaDiaTable;
use App\Models\ConfiguracaoBarbearia;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Services\DisponibilidadeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug: horários que o profissional configura fora da janela padrão do
 * estabelecimento não apareciam nem na agenda do painel nem na área pública.
 * Também cobre o intervalo de 10 min nas configurações gerais.
 *
 * 2026-07-20 = segunda (dayOfWeek 1).
 */
class HorarioProfissionalAgendaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-07-14 08:00'));
        app()->forgetInstance('current_tenant');

        $this->tenant = Tenant::forceCreate(['slug' => 'h-'.uniqid(), 'nome' => 'Salão']);
        app()->instance('current_tenant', $this->tenant);

        ConfiguracaoBarbearia::forceCreate([
            'nome_barbearia' => 'Salão', 'horario_abertura' => '08:00',
            'horario_encerramento' => '18:00', 'intervalo_minutos' => 30,
            'mensalista_limite_cortes_semana' => 1, 'tenant_id' => $this->tenant->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function profissional(array $attrs = []): Profissional
    {
        return Profissional::forceCreate(array_merge([
            'nome' => 'Ana', 'ativo' => true,
            'dias_trabalho' => [0, 1, 2, 3, 4, 5, 6],
            'tenant_id' => $this->tenant->id,
        ], $attrs));
    }

    // ─── Público (DisponibilidadeService, modo lista) ─────────────────────────

    public function test_publico_oferece_horario_do_profissional_apos_o_encerramento(): void
    {
        // Estabelecimento fecha 18:00, mas o profissional atende às 18:30
        $prof = $this->profissional(['horarios_trabalho' => ['08:00', '18:30']]);

        $slots = app(DisponibilidadeService::class)->calcular(
            $prof, 30, Carbon::parse('2026-07-20'), '08:00', '18:00', 30
        );
        $horas = collect($slots)->pluck('hora');

        $this->assertTrue($horas->contains('18:30'), 'O horário 18:30 configurado no profissional deveria aparecer.');
        $this->assertTrue($horas->contains('08:00'));
    }

    // ─── Agenda do painel ──────────────────────────────────────────────────────

    public function test_agenda_mostra_horario_do_profissional_fora_da_janela(): void
    {
        // Config vai até 18:00; profissional configurou também 20:00
        $prof = $this->profissional(['horarios_trabalho' => ['08:00', '20:00']]);

        $comp = new AgendaDiaTable;
        $comp->profissionalId = $prof->id;
        $comp->dataSelecionada = '2026-07-20';

        $horas = collect($comp->getSlots())->pluck('hora');

        $this->assertTrue($horas->contains('20:00'), 'A agenda deveria mostrar o horário 20:00 do profissional.');
    }

    public function test_agenda_sem_horarios_configurados_usa_a_janela_da_config(): void
    {
        $prof = $this->profissional(); // sem horarios_trabalho → grade da config

        $comp = new AgendaDiaTable;
        $comp->profissionalId = $prof->id;
        $comp->dataSelecionada = '2026-07-20';

        $horas = collect($comp->getSlots())->pluck('hora');

        $this->assertTrue($horas->contains('08:00'));
        $this->assertFalse($horas->contains('20:00')); // não inventa horários fora da janela
    }

    // ─── Intervalo de 10 minutos ───────────────────────────────────────────────

    public function test_intervalo_de_10_minutos_gera_slots_de_10_em_10(): void
    {
        $prof = $this->profissional(); // gap-based usa o intervalo da config

        $slots = app(DisponibilidadeService::class)->calcular(
            $prof, 10, Carbon::parse('2026-07-20'), '08:00', '09:00', 10
        );
        $horas = collect($slots)->pluck('hora')->all();

        $this->assertContains('08:00', $horas);
        $this->assertContains('08:10', $horas);
        $this->assertContains('08:20', $horas);
    }
}
