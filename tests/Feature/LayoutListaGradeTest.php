<?php

namespace Tests\Feature;

use App\Filament\Resources\Profissionais\Pages\ListProfissionais;
use App\Filament\Resources\Servicos\Pages\ListServicos;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Toggle lista/grade nas listagens de Serviços e Profissionais:
 * ambas renderizam nos dois layouts e o botão alterna o estado.
 */
class LayoutListaGradeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        app()->forgetInstance('current_tenant');

        $this->tenant = Tenant::forceCreate(['slug' => 'p-'.uniqid(), 'nome' => 'Salão']);
        app()->instance('current_tenant', $this->tenant);

        Servico::forceCreate([
            'nome' => 'Corte', 'preco' => 40, 'duracao_minutos' => 30,
            'ordem' => 1, 'tenant_id' => $this->tenant->id,
        ]);
        Profissional::forceCreate(['nome' => 'Ana', 'tenant_id' => $this->tenant->id]);

        $this->admin = User::forceCreate([
            'name' => 'Dono', 'email' => 'p-'.uniqid().'@x.com',
            'password' => Hash::make('secret123'), 'role' => 'admin', 'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_servicos_lista_e_grade_renderizam_e_alternam(): void
    {
        $comp = Livewire::actingAs($this->admin, 'admin')
            ->test(ListServicos::class)
            ->assertOk()
            ->assertSee('Corte');

        $this->assertSame('lista', $comp->get('layoutView'));

        $comp->callAction('toggleLayout')
            ->assertOk()
            ->assertSee('Corte');

        $this->assertSame('grade', $comp->get('layoutView'));

        // Volta para lista
        $comp->callAction('toggleLayout');
        $this->assertSame('lista', $comp->get('layoutView'));
    }

    public function test_profissionais_lista_e_grade_renderizam_e_alternam(): void
    {
        $comp = Livewire::actingAs($this->admin, 'admin')
            ->test(ListProfissionais::class)
            ->assertOk()
            ->assertSee('Ana');

        $this->assertSame('lista', $comp->get('layoutView'));

        $comp->callAction('toggleLayout')
            ->assertOk()
            ->assertSee('Ana');

        $this->assertSame('grade', $comp->get('layoutView'));
    }
}
