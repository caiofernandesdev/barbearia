<?php

namespace Tests\Feature;

use App\Filament\Resources\Agendamentos\Pages\CreateAgendamento;
use App\Filament\Resources\Agendamentos\Pages\EditAgendamento;
use App\Models\Agendamento;
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
 * Form do recurso Agendamentos: telefone opcional e multi-serviço (pivot +
 * totais somados).
 */
class AgendamentoResourceFormTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Profissional $prof;

    private Servico $corte;

    private Servico $barba;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-08-10 09:00'));
        app()->forgetInstance('current_tenant');

        $this->tenant = Tenant::forceCreate(['slug' => 'p-'.uniqid(), 'nome' => 'Salão']);
        app()->instance('current_tenant', $this->tenant);
        $this->prof = Profissional::forceCreate(['nome' => 'Ana', 'ativo' => true, 'tenant_id' => $this->tenant->id]);
        $this->corte = Servico::forceCreate(['nome' => 'Corte', 'preco' => 40, 'duracao_minutos' => 30, 'ordem' => 1, 'tenant_id' => $this->tenant->id]);
        $this->barba = Servico::forceCreate(['nome' => 'Barba', 'preco' => 25, 'duracao_minutos' => 60, 'ordem' => 2, 'tenant_id' => $this->tenant->id]);
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

    public function test_cria_agendamento_com_varios_servicos_e_sem_telefone(): void
    {
        Livewire::actingAs($this->admin, 'admin')
            ->test(CreateAgendamento::class)
            ->fillForm([
                'cliente_nome' => 'Cliente Multi',
                'cliente_telefone' => '', // opcional
                'profissional_id' => $this->prof->id,
                'servico_ids' => [$this->corte->id, $this->barba->id],
                'data_hora' => '2026-08-11 10:00',
                'status' => 'pendente',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $ag = Agendamento::withoutGlobalScopes()->where('cliente_nome', 'Cliente Multi')->first();
        $this->assertNotNull($ag);
        $this->assertNull($ag->cliente_telefone);                    // telefone opcional
        $this->assertEquals(65.00, (float) $ag->valor_total);        // 40 + 25
        $this->assertSame(90, $ag->duracao_total_minutos);           // 30 + 60
        $this->assertSame($this->corte->id, $ag->servico_id);        // primeiro serviço
        $this->assertEqualsCanonicalizing(
            [$this->corte->id, $this->barba->id],
            $ag->servicos()->pluck('servicos.id')->all()
        );
    }

    public function test_telefone_guarda_so_digitos(): void
    {
        Livewire::actingAs($this->admin, 'admin')
            ->test(CreateAgendamento::class)
            ->fillForm([
                'cliente_nome' => 'Com Telefone',
                'cliente_telefone' => '(14) 99681-1271',
                'profissional_id' => $this->prof->id,
                'servico_ids' => [$this->corte->id],
                'data_hora' => '2026-08-11 11:00',
                'status' => 'pendente',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $ag = Agendamento::withoutGlobalScopes()->where('cliente_nome', 'Com Telefone')->first();
        $this->assertSame('14996811271', $ag->cliente_telefone);
    }

    public function test_servico_e_obrigatorio(): void
    {
        Livewire::actingAs($this->admin, 'admin')
            ->test(CreateAgendamento::class)
            ->fillForm([
                'cliente_nome' => 'Sem Servico',
                'profissional_id' => $this->prof->id,
                'servico_ids' => [],
                'data_hora' => '2026-08-11 12:00',
                'status' => 'pendente',
            ])
            ->call('create')
            ->assertHasFormErrors(['servico_ids']);
    }

    public function test_edit_pre_seleciona_e_sincroniza_servicos(): void
    {
        $ag = Agendamento::create([
            'cliente_nome' => 'Editar', 'cliente_telefone' => '11999998888',
            'profissional_id' => $this->prof->id, 'servico_id' => $this->corte->id,
            'valor_total' => 40, 'duracao_total_minutos' => 30,
            'data_hora' => '2026-08-12 10:00:00', 'status' => 'confirmado',
            'mensalista' => false, 'tenant_id' => $this->tenant->id,
        ]);
        $ag->servicos()->attach($this->corte->id);

        Livewire::actingAs($this->admin, 'admin')
            ->test(EditAgendamento::class, ['record' => $ag->getKey()])
            ->assertFormSet(['servico_ids' => [$this->corte->id]])   // pré-selecionado
            ->fillForm(['servico_ids' => [$this->corte->id, $this->barba->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $ag->refresh();
        $this->assertEquals(65.00, (float) $ag->valor_total);
        $this->assertSame(90, $ag->duracao_total_minutos);
        $this->assertEqualsCanonicalizing(
            [$this->corte->id, $this->barba->id],
            $ag->servicos()->pluck('servicos.id')->all()
        );
    }
}
