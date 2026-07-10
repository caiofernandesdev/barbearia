<?php

namespace Tests\Feature;

use App\Livewire\Admin\AgendaDiaTable;
use App\Models\Agendamento;
use App\Models\ConfiguracaoBarbearia;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Agenda do dia (painel do barbeiro): um atendimento bloqueia TODOS os
 * slots que a duração dele cobre, não só o slot inicial.
 */
class AgendaDiaSlotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_atendimento_de_1h_ocupa_os_4_slots_de_15min(): void
    {
        Queue::fake();
        app()->forgetInstance('current_tenant');

        $tenant = Tenant::forceCreate(['slug' => 'agenda-'.uniqid(), 'nome' => 'Teste']);
        app()->instance('current_tenant', $tenant);

        ConfiguracaoBarbearia::forceCreate([
            'nome_barbearia' => 'Teste', 'horario_abertura' => '09:00',
            'horario_encerramento' => '12:00', 'intervalo_minutos' => 15,
            'mensalista_limite_cortes_semana' => 1, 'tenant_id' => $tenant->id,
        ]);

        $prof = Profissional::forceCreate(['nome' => 'João', 'tenant_id' => $tenant->id]);
        $servico = Servico::forceCreate([
            'nome' => 'Corte com Barba', 'preco' => 65, 'duracao_minutos' => 60,
            'tenant_id' => $tenant->id,
        ]);

        Agendamento::forceCreate([
            'cliente_nome' => 'Andrei', 'cliente_telefone' => '11987654321',
            'profissional_id' => $prof->id, 'servico_id' => $servico->id,
            'data_hora' => now()->addDay()->setTime(10, 0),
            'status' => 'confirmado', 'tenant_id' => $tenant->id,
        ]);

        $componente = new AgendaDiaTable;
        $componente->profissionalId = $prof->id;
        $componente->dataSelecionada = now()->addDay()->format('Y-m-d');

        $slots = collect($componente->getSlots())->keyBy('hora');

        // Os 4 slots de 15min cobertos pela 1h ficam ocupados
        foreach (['10:00', '10:15', '10:30', '10:45'] as $hora) {
            $this->assertTrue($slots[$hora]['ocupado'], "slot {$hora} deveria estar ocupado");
            $this->assertSame('Andrei', $slots[$hora]['cliente']);
        }

        // Início mostra o serviço; continuação é marcada como tal
        $this->assertSame('Corte com Barba', $slots['10:00']['servico']);
        $this->assertSame('⤷ continuação', $slots['10:15']['servico']);

        // Vizinhos continuam livres
        $this->assertFalse($slots['09:45']['ocupado']);
        $this->assertFalse($slots['11:00']['ocupado']);
    }
}
