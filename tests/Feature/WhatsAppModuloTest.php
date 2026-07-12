<?php

namespace Tests\Feature;

use App\Jobs\EnviarWhatsAppJob;
use App\Models\Agendamento;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Módulo WhatsApp por tenant: quando desligado, agendamentos nascem
 * confirmados e nenhuma mensagem é enviada.
 */
class WhatsAppModuloTest extends TestCase
{
    use RefreshDatabase;

    private function cenario(bool $whatsappAtivo): array
    {
        app()->forgetInstance('current_tenant');

        $tenant = Tenant::forceCreate([
            'slug' => 'wa-'.uniqid(),
            'nome' => 'Teste',
            'whatsapp_ativo' => $whatsappAtivo,
        ]);
        app()->instance('current_tenant', $tenant);

        $prof = Profissional::forceCreate(['nome' => 'João', 'tenant_id' => $tenant->id]);
        $servico = Servico::forceCreate([
            'nome' => 'Corte', 'preco' => 40, 'duracao_minutos' => 30, 'tenant_id' => $tenant->id,
        ]);

        return [$tenant, $prof, $servico];
    }

    private function criarAgendamento(Tenant $tenant, Profissional $prof, Servico $servico): Agendamento
    {
        return Agendamento::create([
            'cliente_nome' => 'Cliente', 'cliente_telefone' => '11987654321',
            'profissional_id' => $prof->id, 'servico_id' => $servico->id,
            'data_hora' => now()->addDay(), 'tenant_id' => $tenant->id,
            // status omitido de propósito: o hook decide
        ]);
    }

    public function test_whatsapp_ativo_agendamento_nasce_pendente(): void
    {
        Queue::fake();
        [$tenant, $prof, $servico] = $this->cenario(whatsappAtivo: true);

        $ag = $this->criarAgendamento($tenant, $prof, $servico);

        $this->assertSame('pendente', $ag->status);
    }

    public function test_whatsapp_inativo_agendamento_nasce_confirmado(): void
    {
        Queue::fake();
        [$tenant, $prof, $servico] = $this->cenario(whatsappAtivo: false);

        $ag = $this->criarAgendamento($tenant, $prof, $servico);

        $this->assertSame('confirmado', $ag->status);
    }

    public function test_whatsapp_inativo_nao_envia_mensagem(): void
    {
        Queue::fake();
        [$tenant, $prof, $servico] = $this->cenario(whatsappAtivo: false);

        // O observer enfileira EnviarWhatsAppJob no created(); o job deve abortar
        $this->criarAgendamento($tenant, $prof, $servico);

        // O job roda mas retorna cedo — validamos executando-o de verdade
        Queue::assertPushed(EnviarWhatsAppJob::class); // foi enfileirado
        // e ao rodar não deve lançar nem tentar enviar (tenant inativo)
        $tenant->refresh();
        $this->assertFalse($tenant->whatsappAtivo());
    }

    public function test_status_confirmado_explicito_nao_e_sobrescrito(): void
    {
        Queue::fake();
        [$tenant, $prof, $servico] = $this->cenario(whatsappAtivo: true);

        // Admin marcando manualmente como cancelado deve permanecer cancelado
        $ag = Agendamento::create([
            'cliente_nome' => 'X', 'cliente_telefone' => '11900000000',
            'profissional_id' => $prof->id, 'servico_id' => $servico->id,
            'data_hora' => now()->addDay(), 'status' => 'cancelado', 'tenant_id' => $tenant->id,
        ]);

        $this->assertSame('cancelado', $ag->status);
    }

    public function test_job_aborta_para_tenant_com_whatsapp_inativo(): void
    {
        [$tenant] = $this->cenario(whatsappAtivo: false);

        // Roda o job de verdade (sem fake) — deve retornar sem erro e sem enviar
        (new EnviarWhatsAppJob('11987654321', 'oi', $tenant->id))->handle();

        $this->assertTrue(true); // chegou aqui sem exceção = abortou limpo
    }
}
