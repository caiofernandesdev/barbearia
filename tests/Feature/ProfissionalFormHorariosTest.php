<?php

namespace Tests\Feature;

use App\Filament\Resources\Profissionais\Pages\CreateProfissional;
use App\Filament\Resources\Profissionais\Pages\EditProfissional;
use App\Models\Plano;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Formulário do profissional gravando horários por dia da semana.
 * O ponto sensível é o dot-notation (horarios_por_dia.1) caindo certo
 * dentro da coluna JSON.
 */
class ProfissionalFormHorariosTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        app()->forgetInstance('current_tenant');

        $plano = Plano::forceCreate(['nome' => 'P-'.uniqid(), 'preco_mensal' => 100, 'features' => [], 'ativo' => true]);
        $this->tenant = Tenant::forceCreate(['slug' => 'pf-'.uniqid(), 'nome' => 'Salão', 'plano_id' => $plano->id]);
        app()->instance('current_tenant', $this->tenant);

        $admin = User::forceCreate([
            'name' => 'Dono', 'email' => 'pf-'.uniqid().'@x.com',
            'password' => Hash::make('secret123'), 'role' => 'admin', 'tenant_id' => $this->tenant->id,
        ]);
        $this->actingAs($admin, 'admin');
    }

    public function test_cria_profissional_com_horarios_por_dia(): void
    {
        Livewire::test(CreateProfissional::class)
            ->fillForm([
                'nome' => 'Ana',
                'telefone' => '11999998888',
                'limite_mensalistas' => 10,
                'comissao_percentual' => 0,
                'ativo' => true,
                'dias_trabalho' => [1, 6],
                'horarios_por_dia_ativo' => true,
                'horarios_por_dia' => [
                    1 => ['08:00', '09:00'],
                    6 => ['14:00'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $p = Profissional::where('nome', 'Ana')->firstOrFail();

        $this->assertTrue($p->horarios_por_dia_ativo);
        $this->assertSame(['08:00', '09:00'], $p->horariosDoDia(1));
        $this->assertSame(['14:00'], $p->horariosDoDia(6));
    }

    public function test_edita_e_mantem_os_horarios_de_cada_dia(): void
    {
        $p = Profissional::forceCreate([
            'nome' => 'Bia', 'telefone' => '11888887777', 'ativo' => true,
            'dias_trabalho' => [1, 2], 'horarios_por_dia_ativo' => true,
            'horarios_por_dia' => [1 => ['08:00'], 2 => ['10:00']],
            'tenant_id' => $this->tenant->id,
        ]);

        Livewire::test(EditProfissional::class, ['record' => $p->getRouteKey()])
            ->fillForm(['horarios_por_dia' => [1 => ['08:00', '08:30'], 2 => ['10:00']]])
            ->call('save')
            ->assertHasNoFormErrors();

        $p->refresh();
        $this->assertSame(['08:00', '08:30'], $p->horariosDoDia(1));
        $this->assertSame(['10:00'], $p->horariosDoDia(2), 'terça não podia ser afetada');
    }

    public function test_modo_lista_unica_continua_gravando_normal(): void
    {
        Livewire::test(CreateProfissional::class)
            ->fillForm([
                'nome' => 'Carla',
                'telefone' => '11777776666',
                'limite_mensalistas' => 10,
                'comissao_percentual' => 0,
                'ativo' => true,
                'dias_trabalho' => [1, 2, 3],
                'horarios_por_dia_ativo' => false,
                'horarios_trabalho' => ['08:00', '09:00'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $p = Profissional::where('nome', 'Carla')->firstOrFail();

        $this->assertFalse($p->horarios_por_dia_ativo);
        // Mesma lista vale para todos os dias
        $this->assertSame(['08:00', '09:00'], $p->horariosDoDia(1));
        $this->assertSame(['08:00', '09:00'], $p->horariosDoDia(3));
    }
}
