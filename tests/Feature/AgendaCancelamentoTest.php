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
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cancelar um agendamento clicando no horário da agenda.
 * A permissão é a mesma do Meu Painel: admin sempre pode; profissional
 * só com a flag pode_cancelar.
 */
class AgendaCancelamentoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Profissional $prof;

    private Servico $servico;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-15 08:00'));
        app()->forgetInstance('current_tenant');

        $this->tenant = Tenant::forceCreate(['slug' => 'ac-'.uniqid(), 'nome' => 'Salão']);
        app()->instance('current_tenant', $this->tenant);

        ConfiguracaoBarbearia::forceCreate([
            'nome_barbearia' => 'Salão', 'horario_abertura' => '08:00',
            'horario_encerramento' => '18:00', 'intervalo_minutos' => 60,
            'mensalista_limite_cortes_semana' => 1, 'tenant_id' => $this->tenant->id,
        ]);

        $this->prof = Profissional::forceCreate(['nome' => 'Ana', 'tenant_id' => $this->tenant->id]);
        $this->servico = Servico::forceCreate([
            'nome' => 'Corte', 'preco' => 40, 'duracao_minutos' => 60,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function agendamento(string $status = 'confirmado'): Agendamento
    {
        return Agendamento::forceCreate([
            'cliente_nome' => 'Maria', 'cliente_telefone' => '11987654321',
            'profissional_id' => $this->prof->id, 'servico_id' => $this->servico->id,
            'data_hora' => Carbon::parse('2026-07-16 10:00'),
            'status' => $status, 'tenant_id' => $this->tenant->id,
        ]);
    }

    private function usuario(string $role, bool $podeCancelar = false): User
    {
        return User::forceCreate([
            'name' => 'U', 'email' => 'ac-'.uniqid().'@x.com',
            'password' => Hash::make('secret123'), 'role' => $role,
            'pode_cancelar' => $podeCancelar,
            'profissional_id' => $role === 'barbeiro' ? $this->prof->id : null,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    private function componente(): Testable
    {
        return Livewire::test(AgendaDiaTable::class, ['profissionalId' => $this->prof->id])
            ->set('dataSelecionada', '2026-07-16');
    }

    // ─── Permissão ────────────────────────────────────────────────────────────

    public function test_admin_cancela_pela_agenda(): void
    {
        $ag = $this->agendamento();
        $this->actingAs($this->usuario('admin'), 'admin');

        $this->componente()
            ->call('abrirCancelamento', $ag->id)
            ->assertSet('showCancelModal', true)
            ->call('confirmarCancelamento')
            ->assertSet('showCancelModal', false);

        $this->assertSame('cancelado', $ag->fresh()->status);
    }

    public function test_profissional_com_permissao_cancela(): void
    {
        $ag = $this->agendamento();
        $this->actingAs($this->usuario('barbeiro', podeCancelar: true), 'admin');

        $this->componente()->call('abrirCancelamento', $ag->id)->call('confirmarCancelamento');

        $this->assertSame('cancelado', $ag->fresh()->status);
    }

    public function test_profissional_sem_permissao_nao_cancela(): void
    {
        $ag = $this->agendamento();
        $this->actingAs($this->usuario('barbeiro', podeCancelar: false), 'admin');

        // Nem abre o modal, nem cancela se chamarem o método direto
        $this->componente()
            ->call('abrirCancelamento', $ag->id)
            ->assertSet('showCancelModal', false)
            ->set('cancelarId', $ag->id)
            ->call('confirmarCancelamento');

        $this->assertSame('confirmado', $ag->fresh()->status, 'não podia ter cancelado');
    }

    // ─── Regras do agendamento ────────────────────────────────────────────────

    public function test_concluido_nao_pode_ser_cancelado(): void
    {
        $ag = $this->agendamento('concluido');
        $this->actingAs($this->usuario('admin'), 'admin');

        $this->componente()
            ->call('abrirCancelamento', $ag->id)
            ->assertSet('showCancelModal', false)
            ->set('cancelarId', $ag->id)
            ->call('confirmarCancelamento');

        $this->assertSame('concluido', $ag->fresh()->status);
    }

    // ─── Slot ─────────────────────────────────────────────────────────────────

    public function test_slot_ocupado_expoe_o_agendamento_para_o_admin(): void
    {
        $ag = $this->agendamento();
        $this->actingAs($this->usuario('admin'), 'admin');

        $c = new AgendaDiaTable;
        $c->profissionalId = $this->prof->id;
        $c->dataSelecionada = '2026-07-16';
        $slots = collect($c->getSlots())->keyBy('hora');

        $this->assertTrue($slots['10:00']['ocupado']);
        $this->assertSame($ag->id, $slots['10:00']['agendamento_id']);
        $this->assertTrue($slots['10:00']['cancelavel']);
        // Slot livre não carrega agendamento
        $this->assertNull($slots['09:00']['agendamento_id']);
        $this->assertFalse($slots['09:00']['cancelavel']);
    }

    public function test_slot_nao_e_cancelavel_sem_permissao(): void
    {
        $this->agendamento();
        $this->actingAs($this->usuario('barbeiro', podeCancelar: false), 'admin');

        $c = new AgendaDiaTable;
        $c->profissionalId = $this->prof->id;
        $c->dataSelecionada = '2026-07-16';
        $slots = collect($c->getSlots())->keyBy('hora');

        $this->assertTrue($slots['10:00']['ocupado'], 'continua aparecendo como ocupado');
        $this->assertFalse($slots['10:00']['cancelavel'], 'mas não vira botão');
    }
}
