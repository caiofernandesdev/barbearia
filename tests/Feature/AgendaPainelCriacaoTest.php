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
 * Criação de agendamento pela agenda do painel (dono/profissional):
 * pode marcar VÁRIAS sessões para o mesmo cliente em horários diferentes;
 * só bloqueia sobreposição de horário do mesmo profissional.
 */
class AgendaPainelCriacaoTest extends TestCase
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
        Carbon::setTestNow(Carbon::parse('2026-07-10 08:00'));
        app()->forgetInstance('current_tenant');

        $this->tenant = Tenant::forceCreate(['slug' => 'p-'.uniqid(), 'nome' => 'Salão']);
        app()->instance('current_tenant', $this->tenant);
        ConfiguracaoBarbearia::forceCreate([
            'nome_barbearia' => 'Salão', 'horario_abertura' => '08:00', 'horario_encerramento' => '19:00',
            'intervalo_minutos' => 30, 'mensalista_limite_cortes_semana' => 1, 'tenant_id' => $this->tenant->id,
        ]);
        $this->prof = Profissional::forceCreate(['nome' => 'Ana', 'tenant_id' => $this->tenant->id]);
        $this->servico = Servico::forceCreate(['nome' => 'Manutenção', 'preco' => 40, 'duracao_minutos' => 60, 'tenant_id' => $this->tenant->id]);
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

    private function marcar(string $data, string $hora): void
    {
        Livewire::actingAs($this->admin, 'admin')
            ->test(AgendaDiaTable::class, ['profissionalId' => $this->prof->id])
            ->set('dataSelecionada', $data)
            ->set('horaSelecionada', $hora)
            ->set('clienteNome', 'Maria')
            ->set('clienteTelefone', '11987654321')
            ->set('servicoId', $this->servico->id)
            ->call('salvarAgendamento');
    }

    public function test_mesmo_cliente_pode_ter_agendamentos_em_terças_diferentes(): void
    {
        $this->marcar('2026-07-13', '10:00'); // dia 13
        $this->marcar('2026-07-21', '10:00'); // dia 21 — não deve barrar!

        $ags = Agendamento::withoutGlobalScopes()->where('cliente_telefone', '11987654321')->get();
        $this->assertCount(2, $ags);
    }

    public function test_bloqueia_sobreposicao_de_horario_do_mesmo_profissional(): void
    {
        $this->marcar('2026-07-13', '10:00'); // ocupa 10:00–11:00
        // Outro cliente às 10:30 (dentro do intervalo) — deve barrar
        Livewire::actingAs($this->admin, 'admin')
            ->test(AgendaDiaTable::class, ['profissionalId' => $this->prof->id])
            ->set('dataSelecionada', '2026-07-13')
            ->set('horaSelecionada', '10:30')
            ->set('clienteNome', 'Outra')
            ->set('clienteTelefone', '11900000000')
            ->set('servicoId', $this->servico->id)
            ->call('salvarAgendamento');

        $this->assertSame(1, Agendamento::withoutGlobalScopes()->count());
    }
}
