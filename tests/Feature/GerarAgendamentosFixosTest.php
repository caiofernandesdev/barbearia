<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Mensalista;
use App\Models\MensalistaHorarioFixo;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\Tenant;
use App\Services\GerarAgendamentosFixosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GerarAgendamentosFixosTest extends TestCase
{
    use RefreshDatabase;

    private function cenario(bool $whatsappAtivo = true): array
    {
        app()->forgetInstance('current_tenant');
        $tenant = Tenant::forceCreate(['slug' => 'gf-'.uniqid(), 'nome' => 'Salão', 'whatsapp_ativo' => $whatsappAtivo]);
        app()->instance('current_tenant', $tenant);

        $prof = Profissional::forceCreate(['nome' => 'Ana', 'tenant_id' => $tenant->id]);
        $servico = Servico::forceCreate(['nome' => 'Pé e mão', 'preco' => 50, 'duracao_minutos' => 60, 'tenant_id' => $tenant->id]);
        $mensalista = Mensalista::forceCreate([
            'nome' => 'Claudia', 'telefone' => '14991767055', 'tipo' => 'mensalista_fixo', 'tenant_id' => $tenant->id,
        ]);
        MensalistaHorarioFixo::forceCreate([
            'mensalista_id' => $mensalista->id, 'profissional_id' => $prof->id, 'servico_id' => $servico->id,
            'dia_semana' => now()->addDay()->dayOfWeek, 'hora' => '08:00', 'ativo' => true, 'tenant_id' => $tenant->id,
        ]);

        return [$tenant, $mensalista];
    }

    public function test_gera_agendamentos_das_proximas_semanas(): void
    {
        Queue::fake();
        [, $mensalista] = $this->cenario();

        $criados = app(GerarAgendamentosFixosService::class)->gerar($mensalista, semanas: 8);

        // ~8 ocorrências semanais (o dia da semana escolhido é amanhã)
        $this->assertGreaterThanOrEqual(7, $criados);
        $ags = Agendamento::withoutGlobalScopes()->where('mensalista_id', $mensalista->id)->get();
        $this->assertTrue($ags->every(fn ($a) => $a->data_hora->format('H:i') === '08:00'));
        $this->assertTrue($ags->every(fn ($a) => $a->mensalista === true));
        $this->assertTrue($ags->every(fn ($a) => $a->status === 'pendente')); // whatsapp on
    }

    public function test_nao_duplica_ao_rodar_duas_vezes(): void
    {
        Queue::fake();
        [, $mensalista] = $this->cenario();

        $service = app(GerarAgendamentosFixosService::class);
        $primeira = $service->gerar($mensalista, semanas: 8);
        $segunda = $service->gerar($mensalista, semanas: 8);

        $this->assertGreaterThan(0, $primeira);
        $this->assertSame(0, $segunda, 'A segunda rodada não deve duplicar');
    }

    public function test_whatsapp_desligado_gera_confirmado(): void
    {
        Queue::fake();
        [, $mensalista] = $this->cenario(whatsappAtivo: false);

        app(GerarAgendamentosFixosService::class)->gerar($mensalista, semanas: 2);

        $ag = Agendamento::withoutGlobalScopes()->where('mensalista_id', $mensalista->id)->first();
        $this->assertSame('confirmado', $ag->status);
    }

    public function test_ignora_mensalista_que_nao_e_fixo(): void
    {
        Queue::fake();
        app()->forgetInstance('current_tenant');
        $tenant = Tenant::forceCreate(['slug' => 'gf-'.uniqid(), 'nome' => 'X']);
        app()->instance('current_tenant', $tenant);
        $m = Mensalista::forceCreate(['nome' => 'Y', 'telefone' => '11900001111', 'tipo' => 'mensalista', 'tenant_id' => $tenant->id]);

        $criados = app(GerarAgendamentosFixosService::class)->gerar($m);

        $this->assertSame(0, $criados);
    }
}
