<?php

namespace Tests\Feature;

use App\Filament\Resources\Agendamentos\Pages\ListAgendamentos;
use App\Filament\Resources\ListasEspera\Pages\ListListasEspera;
use App\Filament\Resources\Mensalistas\Pages\ListMensalistas;
use App\Models\Agendamento;
use App\Models\ListaEspera;
use App\Models\Mensalista;
use App\Models\Plano;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Toggle lista/grade em Agendamentos, Mensalistas e Listas de Espera:
 * cada listagem renderiza nos dois layouts e o botão alterna.
 */
class GradeListagensTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        app()->forgetInstance('current_tenant');

        $plano = Plano::forceCreate([
            'nome' => 'P-'.uniqid(), 'preco_mensal' => 100,
            'features' => ['mensalistas', 'lista_espera'], 'ativo' => true,
        ]);
        $this->tenant = Tenant::forceCreate(['slug' => 'p-'.uniqid(), 'nome' => 'Salão', 'plano_id' => $plano->id]);
        app()->instance('current_tenant', $this->tenant);

        $prof = Profissional::forceCreate(['nome' => 'Ana', 'ativo' => true, 'tenant_id' => $this->tenant->id]);
        $servico = Servico::forceCreate(['nome' => 'Corte', 'preco' => 40, 'duracao_minutos' => 30, 'ordem' => 1, 'tenant_id' => $this->tenant->id]);

        Mensalista::forceCreate([
            'nome' => 'Cliente Fixo', 'telefone' => '11988887777', 'tipo' => 'mensalista_fixo',
            'limite_cortes_semana' => 1, 'valor_mensalidade' => 90, 'tenant_id' => $this->tenant->id,
        ]);

        Agendamento::create([
            'cliente_nome' => 'João Agenda', 'cliente_telefone' => '11977776666',
            'profissional_id' => $prof->id, 'servico_id' => $servico->id,
            'valor_total' => 40, 'duracao_total_minutos' => 30,
            'data_hora' => now()->addDay()->setTime(10, 0), 'status' => 'confirmado',
            'mensalista' => false, 'tenant_id' => $this->tenant->id,
        ]);

        ListaEspera::forceCreate([
            'tenant_id' => $this->tenant->id, 'profissional_id' => $prof->id, 'servico_id' => $servico->id,
            'cliente_nome' => 'Maria Espera', 'cliente_telefone' => '11966665555',
            'data' => now()->addDays(2)->format('Y-m-d'), 'hora_preferida' => '14:00', 'status' => 'aguardando',
        ]);

        $this->admin = User::forceCreate([
            'name' => 'Dono', 'email' => 'p-'.uniqid().'@x.com',
            'password' => Hash::make('secret123'), 'role' => 'admin', 'tenant_id' => $this->tenant->id,
        ]);
    }

    private function alternaLayout(string $pagina, string $visivel): void
    {
        $comp = Livewire::actingAs($this->admin, 'admin')
            ->test($pagina)
            ->assertOk()
            ->assertSee($visivel);

        $this->assertSame('lista', $comp->get('layoutView'));

        $comp->callAction('toggleLayout')
            ->assertOk()
            ->assertSee($visivel);

        $this->assertSame('grade', $comp->get('layoutView'));

        $comp->callAction('toggleLayout');
        $this->assertSame('lista', $comp->get('layoutView'));
    }

    public function test_agendamentos_alterna_lista_e_grade(): void
    {
        $this->alternaLayout(ListAgendamentos::class, 'João Agenda');
    }

    public function test_mensalistas_alterna_lista_e_grade(): void
    {
        $this->alternaLayout(ListMensalistas::class, 'Cliente Fixo');
    }

    public function test_listas_espera_alterna_lista_e_grade(): void
    {
        $this->alternaLayout(ListListasEspera::class, 'Maria Espera');
    }
}
