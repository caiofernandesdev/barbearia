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
 * Agenda do painel: marcar em horário que já passou (com aviso) e inverter
 * (trocar) o horário de dois clientes.
 */
class AgendaTrocaEPassadoTest extends TestCase
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
        Carbon::setTestNow(Carbon::parse('2026-08-05 14:00')); // quarta, 14h
        app()->forgetInstance('current_tenant');

        $this->tenant = Tenant::forceCreate(['slug' => 'p-'.uniqid(), 'nome' => 'Salão']);
        app()->instance('current_tenant', $this->tenant);
        ConfiguracaoBarbearia::forceCreate([
            'nome_barbearia' => 'Salão', 'horario_abertura' => '08:00', 'horario_encerramento' => '19:00',
            'intervalo_minutos' => 60, 'mensalista_limite_cortes_semana' => 1, 'tenant_id' => $this->tenant->id,
        ]);
        $this->prof = Profissional::forceCreate([
            'nome' => 'Ana', 'tenant_id' => $this->tenant->id,
            'dias_trabalho' => [0, 1, 2, 3, 4, 5, 6],
        ]);
        $this->servico = Servico::forceCreate([
            'nome' => 'Corte', 'preco' => 40, 'duracao_minutos' => 60, 'tenant_id' => $this->tenant->id,
        ]);
        $this->admin = User::forceCreate([
            'name' => 'Dono', 'email' => 'p-'.uniqid().'@x.com',
            'password' => Hash::make('secret123'), 'role' => 'admin', 'tenant_id' => $this->tenant->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function componente()
    {
        return Livewire::actingAs($this->admin, 'admin')
            ->test(AgendaDiaTable::class, ['profissionalId' => $this->prof->id])
            ->set('dataSelecionada', '2026-08-05');
    }

    private function novoAgendamento(string $hora, string $cliente): Agendamento
    {
        return Agendamento::create([
            'cliente_nome' => $cliente,
            'cliente_telefone' => '1190000'.rand(1000, 9999),
            'profissional_id' => $this->prof->id,
            'servico_id' => $this->servico->id,
            'valor_total' => 40, 'duracao_total_minutos' => 60,
            'data_hora' => '2026-08-05 '.$hora.':00',
            'status' => 'confirmado', 'mensalista' => false,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    // ─── Marcar em horário que já passou ──────────────────────────────────────

    public function test_slot_passado_de_hoje_vem_marcado_como_passado(): void
    {
        $slots = $this->componente()->instance()->getSlots();

        $slot10h = collect($slots)->firstWhere('hora', '10:00'); // antes das 14h
        $slot16h = collect($slots)->firstWhere('hora', '16:00'); // depois das 14h

        $this->assertTrue($slot10h['passado']);
        $this->assertFalse($slot16h['passado']);
    }

    public function test_consegue_agendar_em_horario_passado(): void
    {
        $this->componente()
            ->mountAction('agendar', arguments: ['hora' => '10:00', 'passado' => true])
            ->setActionData([
                'cliente_nome' => 'Cliente Atrasado',
                'servico_ids' => [$this->servico->id],
            ])
            ->callMountedAction();

        $ag = Agendamento::withoutGlobalScopes()->where('cliente_nome', 'Cliente Atrasado')->first();
        $this->assertNotNull($ag);
        $this->assertSame('2026-08-05 10:00:00', $ag->data_hora->format('Y-m-d H:i:s'));
    }

    // ─── Inverter (trocar) dois agendamentos ──────────────────────────────────

    public function test_inverte_horarios_de_dois_clientes(): void
    {
        $a = $this->novoAgendamento('16:00', 'Cliente A');
        $b = $this->novoAgendamento('17:00', 'Cliente B');

        $this->componente()
            ->mountAction('inverter')
            ->setActionData(['agendamento_a' => $a->id, 'agendamento_b' => $b->id])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame('16:00', $b->refresh()->data_hora->format('H:i')); // B foi pro horário de A
        $this->assertSame('17:00', $a->refresh()->data_hora->format('H:i')); // A foi pro horário de B
    }

    public function test_troca_bloqueia_quando_geraria_conflito_com_terceiro(): void
    {
        $curto = Servico::forceCreate([
            'nome' => 'Franja', 'preco' => 15, 'duracao_minutos' => 30, 'tenant_id' => $this->tenant->id,
        ]);

        // A: 60min às 08:00 (08:00–09:00)
        $a = $this->novoAgendamento('08:00', 'Cliente A');
        // B: 30min às 10:00 (10:00–10:30)
        $b = Agendamento::create([
            'cliente_nome' => 'Cliente B', 'cliente_telefone' => '11900001234',
            'profissional_id' => $this->prof->id, 'servico_id' => $curto->id,
            'valor_total' => 15, 'duracao_total_minutos' => 30,
            'data_hora' => '2026-08-05 10:00:00', 'status' => 'confirmado',
            'mensalista' => false, 'tenant_id' => $this->tenant->id,
        ]);
        // Terceiro (fora da troca): 30min às 10:30 (10:30–11:00)
        $terceiro = Agendamento::create([
            'cliente_nome' => 'Terceiro', 'cliente_telefone' => '11900009999',
            'profissional_id' => $this->prof->id, 'servico_id' => $curto->id,
            'valor_total' => 15, 'duracao_total_minutos' => 30,
            'data_hora' => '2026-08-05 10:30:00', 'status' => 'confirmado',
            'mensalista' => false, 'tenant_id' => $this->tenant->id,
        ]);

        // Trocar A e B: A (60min) iria para 10:00 → 10:00–11:00, batendo no Terceiro (10:30).
        $this->componente()
            ->mountAction('inverter')
            ->setActionData(['agendamento_a' => $a->id, 'agendamento_b' => $b->id])
            ->callMountedAction();

        // Troca bloqueada: horários intactos.
        $this->assertSame('08:00', $a->refresh()->data_hora->format('H:i'));
        $this->assertSame('10:00', $b->refresh()->data_hora->format('H:i'));
        $this->assertSame('10:30', $terceiro->refresh()->data_hora->format('H:i'));
    }
}
