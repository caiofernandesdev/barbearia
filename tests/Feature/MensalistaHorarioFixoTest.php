<?php

namespace Tests\Feature;

use App\Filament\Resources\Mensalistas\Pages\CreateMensalista;
use App\Filament\Resources\Mensalistas\Pages\EditMensalista;
use App\Models\Mensalista;
use App\Models\MensalistaHorarioFixo;
use App\Models\Plano;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class MensalistaHorarioFixoTest extends TestCase
{
    use RefreshDatabase;

    public function test_salva_horario_fixo_ao_criar_mensalista_fixo(): void
    {
        app()->forgetInstance('current_tenant');

        $plano = Plano::forceCreate(['nome' => 'P-'.uniqid(), 'preco_mensal' => 100, 'features' => ['mensalistas'], 'ativo' => true]);
        $tenant = Tenant::forceCreate(['slug' => 'm-'.uniqid(), 'nome' => 'Salão', 'plano_id' => $plano->id]);
        app()->instance('current_tenant', $tenant);

        $admin = User::forceCreate([
            'name' => 'Admin', 'email' => 'm-'.uniqid().'@x.com',
            'password' => Hash::make('secret123'), 'role' => 'admin', 'tenant_id' => $tenant->id,
        ]);
        $prof = Profissional::forceCreate(['nome' => 'Ana', 'tenant_id' => $tenant->id]);
        $servico = Servico::forceCreate(['nome' => 'Unha', 'preco' => 50, 'duracao_minutos' => 60, 'tenant_id' => $tenant->id]);

        Livewire::actingAs($admin, 'admin')
            ->test(CreateMensalista::class)
            ->fillForm([
                'nome' => 'Dona Maria',
                'telefone' => '11987654321',
                'tipo' => 'mensalista_fixo',
                'horariosFixos' => [
                    [
                        'profissional_id' => $prof->id,
                        'servico_id' => $servico->id,
                        'dia_semana' => 2,
                        'hora' => '09:00',
                        'ativo' => true,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $fixos = MensalistaHorarioFixo::withoutGlobalScopes()->get();
        $this->assertCount(1, $fixos, 'O horário fixo deveria ter sido salvo');
        $this->assertSame($prof->id, $fixos->first()->profissional_id);
        $this->assertSame($tenant->id, $fixos->first()->tenant_id);
    }

    public function test_edicao_carrega_e_preserva_a_hora_do_horario_fixo(): void
    {
        app()->forgetInstance('current_tenant');

        $plano = Plano::forceCreate(['nome' => 'P-'.uniqid(), 'preco_mensal' => 100, 'features' => ['mensalistas'], 'ativo' => true]);
        $tenant = Tenant::forceCreate(['slug' => 'm-'.uniqid(), 'nome' => 'Salão', 'plano_id' => $plano->id]);
        app()->instance('current_tenant', $tenant);

        $admin = User::forceCreate([
            'name' => 'Admin', 'email' => 'm-'.uniqid().'@x.com',
            'password' => Hash::make('secret123'), 'role' => 'admin', 'tenant_id' => $tenant->id,
        ]);
        $prof = Profissional::forceCreate(['nome' => 'Ana', 'tenant_id' => $tenant->id]);
        $servico = Servico::forceCreate(['nome' => 'Unha', 'preco' => 50, 'duracao_minutos' => 60, 'tenant_id' => $tenant->id]);

        $mensalista = Mensalista::forceCreate([
            'nome' => 'Maria', 'telefone' => '11987654321', 'tipo' => 'mensalista_fixo', 'tenant_id' => $tenant->id,
        ]);
        $fixo = MensalistaHorarioFixo::forceCreate([
            'mensalista_id' => $mensalista->id, 'profissional_id' => $prof->id, 'servico_id' => $servico->id,
            'dia_semana' => 2, 'hora' => '09:00', 'ativo' => true, 'tenant_id' => $tenant->id,
        ]);

        // Abre a edição e salva sem tocar em nada — a hora deve ser preservada
        Livewire::actingAs($admin, 'admin')
            ->test(EditMensalista::class, ['record' => $mensalista->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $fixo->refresh();
        // Bug: se o form não recarrega a hora (09:00:00 no banco vs opção 09:00),
        // o re-save apaga/invalida a hora
        $this->assertNotEmpty($fixo->hora, 'A hora foi perdida no re-save da edição');
        $this->assertStringStartsWith('09:00', $fixo->hora);
        $this->assertSame(1, MensalistaHorarioFixo::withoutGlobalScopes()->count());
    }
}
