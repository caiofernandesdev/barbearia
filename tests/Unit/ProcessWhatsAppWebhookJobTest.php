<?php

namespace Tests\Unit;

use App\Jobs\EnviarWhatsAppJob;
use App\Jobs\ProcessWhatsAppWebhookJob;
use App\Models\Agendamento;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProcessWhatsAppWebhookJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Intercepta os EnviarWhatsAppJob disparados pelo AgendamentoObserver
        Queue::fake();
        app()->forgetInstance('current_tenant');
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('current_tenant');
        parent::tearDown();
    }

    /** Cria tenant + profissional + serviço + agendamento válidos para o cenário. */
    private function criarAgendamento(array $attrs = []): Agendamento
    {
        // O observer created() vincula o tenant no container; limpa para o
        // TenantScope não filtrar as relações do próximo cenário pelo tenant anterior
        app()->forgetInstance('current_tenant');

        $tenant = Tenant::forceCreate([
            'slug' => 'barbearia-'.uniqid(),
            'nome' => 'Barbearia Teste',
        ]);

        $profissional = Profissional::forceCreate([
            'nome' => 'João Barbeiro',
            'tenant_id' => $tenant->id,
        ]);

        $servico = Servico::forceCreate([
            'nome' => 'Corte',
            'preco' => 50.00,
            'duracao_minutos' => 30,
            'tenant_id' => $tenant->id,
        ]);

        return Agendamento::forceCreate(array_merge([
            'cliente_nome' => 'Cliente Teste',
            'cliente_telefone' => '11987654321',
            'profissional_id' => $profissional->id,
            'servico_id' => $servico->id,
            'data_hora' => now()->addDay()->setTime(10, 0),
            'status' => 'pendente',
            'tenant_id' => $tenant->id,
        ], $attrs));
    }

    private function rodarJob(string $phone, string $texto): void
    {
        (new ProcessWhatsAppWebhookJob($phone, $texto))->handle();
    }

    private function statusDe(Agendamento $agendamento): string
    {
        return Agendamento::withoutGlobalScopes()->findOrFail($agendamento->id)->status;
    }

    // ─── Transições de status ────────────────────────────────────────────────

    public function test_confirma_agendamento_pendente_com_1(): void
    {
        $agendamento = $this->criarAgendamento();

        $this->rodarJob('5511987654321', '1');

        $this->assertSame('confirmado', $this->statusDe($agendamento));
    }

    public function test_confirma_agendamento_pendente_com_sim(): void
    {
        $agendamento = $this->criarAgendamento();

        $this->rodarJob('5511987654321', 'sim');

        $this->assertSame('confirmado', $this->statusDe($agendamento));
    }

    public function test_cancela_agendamento_pendente_com_2(): void
    {
        $agendamento = $this->criarAgendamento();

        $this->rodarJob('5511987654321', '2');

        $this->assertSame('cancelado', $this->statusDe($agendamento));
    }

    public function test_cancela_agendamento_pendente_com_nao_acentuado(): void
    {
        $agendamento = $this->criarAgendamento();

        $this->rodarJob('5511987654321', 'não');

        $this->assertSame('cancelado', $this->statusDe($agendamento));
    }

    public function test_texto_invalido_nao_altera_o_agendamento(): void
    {
        $agendamento = $this->criarAgendamento();

        $this->rodarJob('5511987654321', 'talvez');

        $this->assertSame('pendente', $this->statusDe($agendamento));
    }

    // ─── Matching de telefone (JID do WhatsApp × número salvo no site) ──────

    public function test_jid_com_ddi_encontra_agendamento_salvo_sem_ddi(): void
    {
        $agendamento = $this->criarAgendamento(['cliente_telefone' => '11987654321']);

        $this->rodarJob('5511987654321', '1');

        $this->assertSame('confirmado', $this->statusDe($agendamento));
    }

    public function test_jid_sem_nono_digito_encontra_agendamento_com_nono_digito(): void
    {
        // Números antigos: o WhatsApp devolve o JID sem o 9º dígito
        $agendamento = $this->criarAgendamento(['cliente_telefone' => '11987654321']);

        $this->rodarJob('551187654321', '1');

        $this->assertSame('confirmado', $this->statusDe($agendamento));
    }

    public function test_jid_com_nono_digito_encontra_agendamento_sem_nono_digito(): void
    {
        $agendamento = $this->criarAgendamento(['cliente_telefone' => '1187654321']);

        $this->rodarJob('5511987654321', '1');

        $this->assertSame('confirmado', $this->statusDe($agendamento));
    }

    public function test_telefone_iniciado_em_55_sem_ser_ddi_nao_e_corrompido(): void
    {
        // Regressão do bug do ltrim($phone, '55'), que comia todos os 5s iniciais
        $agendamento = $this->criarAgendamento(['cliente_telefone' => '55987654321']);

        $this->rodarJob('5555987654321', '1');

        $this->assertSame('confirmado', $this->statusDe($agendamento));
    }

    public function test_nao_altera_agendamento_de_outro_telefone(): void
    {
        $agendamento = $this->criarAgendamento(['cliente_telefone' => '11911112222']);

        $this->rodarJob('5511987654321', '1');

        $this->assertSame('pendente', $this->statusDe($agendamento));
    }

    // ─── Seleção do agendamento ──────────────────────────────────────────────

    public function test_nao_altera_agendamento_passado(): void
    {
        $agendamento = $this->criarAgendamento(['data_hora' => now()->subDays(2)]);

        $this->rodarJob('5511987654321', '1');

        $this->assertSame('pendente', $this->statusDe($agendamento));
    }

    public function test_nao_altera_agendamento_ja_confirmado_ou_cancelado(): void
    {
        $confirmado = $this->criarAgendamento(['status' => 'confirmado']);
        $cancelado = $this->criarAgendamento(['status' => 'cancelado']);

        $this->rodarJob('5511987654321', '2');

        $this->assertSame('confirmado', $this->statusDe($confirmado));
        $this->assertSame('cancelado', $this->statusDe($cancelado));
    }

    public function test_com_dois_pendentes_altera_apenas_o_mais_proximo(): void
    {
        $proximo = $this->criarAgendamento(['data_hora' => now()->addDay()->setTime(9, 0)]);
        $distante = $this->criarAgendamento(['data_hora' => now()->addDays(5)->setTime(9, 0)]);

        $this->rodarJob('5511987654321', '1');

        $this->assertSame('confirmado', $this->statusDe($proximo));
        $this->assertSame('pendente', $this->statusDe($distante));
    }

    public function test_sem_agendamento_correspondente_nao_lanca_excecao(): void
    {
        $this->rodarJob('5599999999999', '1');

        $this->assertTrue(true); // chegar aqui sem exceção é o comportamento esperado
    }

    // ─── Efeitos colaterais (observer + tenant) ──────────────────────────────

    public function test_confirmacao_dispara_mensagem_whatsapp_para_o_cliente(): void
    {
        $this->criarAgendamento();
        Queue::fake(); // zera os jobs do created() para contar só os do update

        $this->rodarJob('5511987654321', '1');

        Queue::assertPushed(EnviarWhatsAppJob::class);
    }

    public function test_vincula_o_tenant_do_agendamento_para_o_observer(): void
    {
        $agendamento = $this->criarAgendamento();

        $this->rodarJob('5511987654321', '1');

        $this->assertTrue(app()->bound('current_tenant'));
        $this->assertSame($agendamento->tenant_id, app('current_tenant')->id);
    }

    public function test_worker_reaproveitado_nao_vaza_tenant_do_job_anterior(): void
    {
        $agendamentoA = $this->criarAgendamento(['cliente_telefone' => '11911112222']);
        $agendamentoB = $this->criarAgendamento(['cliente_telefone' => '11933334444']);

        // Simula worker de longa duração: job do tenant A roda primeiro
        $this->rodarJob('5511911112222', '1');
        $this->assertSame($agendamentoA->tenant_id, app('current_tenant')->id);

        // Job do tenant B no mesmo processo deve rebindar o tenant correto
        $this->rodarJob('5511933334444', '1');

        $this->assertSame($agendamentoB->tenant_id, app('current_tenant')->id);
        $this->assertSame('confirmado', $this->statusDe($agendamentoB));
    }

    public function test_job_duplicado_e_inofensivo(): void
    {
        $agendamento = $this->criarAgendamento();

        $this->rodarJob('5511987654321', '1');
        $this->rodarJob('5511987654321', '1'); // retry/duplicata: já não há pendente

        $this->assertSame('confirmado', $this->statusDe($agendamento));
    }

    // ─── Estresse ─────────────────────────────────────────────────────────────

    public function test_estresse_50_confirmacoes_em_sequencia(): void
    {
        $agendamentos = [];
        for ($i = 0; $i < 50; $i++) {
            $agendamentos[] = $this->criarAgendamento([
                'cliente_telefone' => sprintf('119%08d', $i),
            ]);
        }

        $inicio = microtime(true);

        foreach ($agendamentos as $i => $agendamento) {
            $this->rodarJob(sprintf('55119%08d', $i), $i % 2 === 0 ? '1' : '2');
        }

        $duracao = microtime(true) - $inicio;

        foreach ($agendamentos as $i => $agendamento) {
            $this->assertSame(
                $i % 2 === 0 ? 'confirmado' : 'cancelado',
                $this->statusDe($agendamento)
            );
        }

        $this->assertLessThan(30, $duracao, sprintf('50 jobs levaram %.1fs', $duracao));
    }
}
