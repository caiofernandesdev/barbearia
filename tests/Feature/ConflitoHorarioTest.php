<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Anti-double-booking: um profissional não pode ter dois atendimentos que se
 * sobrepõem no tempo (considerando a duração, não só o horário de início).
 */
class ConflitoHorarioTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Profissional $prof;

    private Servico $corte;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-06-15 08:00'));
        app()->forgetInstance('current_tenant');

        $this->tenant = Tenant::forceCreate(['slug' => 'c-'.uniqid(), 'nome' => 'Salão']);
        app()->instance('current_tenant', $this->tenant);

        $this->prof = Profissional::forceCreate(['nome' => 'Ana', 'tenant_id' => $this->tenant->id]);
        $this->corte = Servico::forceCreate(['nome' => 'Corte', 'preco' => 40, 'duracao_minutos' => 60, 'tenant_id' => $this->tenant->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function criar(string $dataHora, int $duracao = 60): Agendamento
    {
        return Agendamento::forceCreate([
            'cliente_nome' => 'X', 'cliente_telefone' => '119'.rand(10000000, 99999999),
            'profissional_id' => $this->prof->id, 'servico_id' => $this->corte->id,
            'data_hora' => $dataHora, 'duracao_total_minutos' => $duracao,
            'status' => 'confirmado', 'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_horario_exato_igual_conflita(): void
    {
        $this->criar('2026-07-01 10:00');

        $this->assertTrue(Agendamento::temConflito($this->prof->id, Carbon::parse('2026-07-01 10:00'), 60, $this->tenant->id));
    }

    public function test_sobreposicao_por_duracao_conflita(): void
    {
        // Atendimento de 1h às 10:00 (ocupa 10:00–11:00)
        $this->criar('2026-07-01 10:00', 60);

        // Tentar às 10:30 (dentro do intervalo) conflita, mesmo sem início igual
        $this->assertTrue(Agendamento::temConflito($this->prof->id, Carbon::parse('2026-07-01 10:30'), 30, $this->tenant->id));
    }

    public function test_horarios_encostados_nao_conflitam(): void
    {
        // 10:00–11:00 ocupado; novo começa exatamente às 11:00 → não sobrepõe
        $this->criar('2026-07-01 10:00', 60);

        $this->assertFalse(Agendamento::temConflito($this->prof->id, Carbon::parse('2026-07-01 11:00'), 60, $this->tenant->id));
    }

    public function test_outro_profissional_nao_conflita(): void
    {
        $this->criar('2026-07-01 10:00', 60);
        $outro = Profissional::forceCreate(['nome' => 'Bruno', 'tenant_id' => $this->tenant->id]);

        $this->assertFalse(Agendamento::temConflito($outro->id, Carbon::parse('2026-07-01 10:00'), 60, $this->tenant->id));
    }

    public function test_cancelado_nao_conflita(): void
    {
        $ag = $this->criar('2026-07-01 10:00', 60);
        $ag->update(['status' => 'cancelado']);

        $this->assertFalse(Agendamento::temConflito($this->prof->id, Carbon::parse('2026-07-01 10:00'), 60, $this->tenant->id));
    }

    public function test_ignora_o_proprio_agendamento_ao_editar(): void
    {
        $ag = $this->criar('2026-07-01 10:00', 60);

        // Editando o próprio: não deve conflitar consigo mesmo
        $this->assertFalse(Agendamento::temConflito($this->prof->id, Carbon::parse('2026-07-01 10:00'), 60, $this->tenant->id, $ag->id));
    }

    public function test_booking_publico_rejeita_horario_ja_ocupado(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->criar('2026-07-01 10:00', 60);

        $resp = $this->post("/{$this->tenant->slug}/agendar", [
            'cliente_nome' => 'Novo Cliente',
            'cliente_telefone' => '11912345678',
            'profissional_id' => $this->prof->id,
            'servico_id' => $this->corte->id,
            'servico_ids' => (string) $this->corte->id,
            'data_hora' => '2026-07-01 10:30:00', // dentro do intervalo ocupado
        ]);

        $resp->assertSessionHasErrors('data_hora');
        // Só o agendamento original existe
        $this->assertSame(1, Agendamento::withoutGlobalScopes()->count());
    }
}
