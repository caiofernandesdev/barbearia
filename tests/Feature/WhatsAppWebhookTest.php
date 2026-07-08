<?php

namespace Tests\Feature;

use App\Jobs\ProcessWhatsAppWebhookJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Testa o endpoint /webhook/whatsapp isoladamente (sem banco):
 * o controller só filtra/valida o payload e despacha o job — Queue::fake
 * intercepta o dispatch, então nenhum teste aqui toca o banco de dados.
 */
class WhatsAppWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'event' => 'messages.upsert',
            'data' => [
                'key' => [
                    'id' => 'MSG-'.uniqid('', true),
                    'remoteJid' => '5511987654321@s.whatsapp.net',
                    'fromMe' => false,
                ],
                'message' => [
                    'conversation' => '1',
                ],
            ],
        ], $overrides);
    }

    // ─── Fluxo feliz ──────────────────────────────────────────────────────────

    public function test_despacha_job_para_confirmacao_com_telefone_extraido_do_jid(): void
    {
        $this->postJson('/webhook/whatsapp', $this->payload())->assertOk();

        Queue::assertPushed(ProcessWhatsAppWebhookJob::class, fn ($job) => $job->phone === '5511987654321' && $job->texto === '1'
        );
    }

    public function test_aceita_evento_com_underscore_messages_upsert(): void
    {
        $this->postJson('/webhook/whatsapp', $this->payload(['event' => 'MESSAGES_UPSERT']))
            ->assertOk();

        Queue::assertPushed(ProcessWhatsAppWebhookJob::class);
    }

    public function test_normaliza_respostas_em_maiusculas_e_com_acento(): void
    {
        // "NÃO" só vira "não" com mb_strtolower — strtolower ignora o Ã multibyte
        $this->postJson('/webhook/whatsapp', $this->payload([
            'data' => ['message' => ['conversation' => '  NÃO  ']],
        ]))->assertOk();

        Queue::assertPushed(ProcessWhatsAppWebhookJob::class, fn ($job) => $job->texto === 'não');
    }

    public function test_aceita_texto_via_extended_text_message(): void
    {
        $this->postJson('/webhook/whatsapp', $this->payload([
            'data' => ['message' => [
                'conversation' => null,
                'extendedTextMessage' => ['text' => 'sim'],
            ]],
        ]))->assertOk();

        Queue::assertPushed(ProcessWhatsAppWebhookJob::class, fn ($job) => $job->texto === 'sim');
    }

    public function test_aceita_resposta_de_botao(): void
    {
        $this->postJson('/webhook/whatsapp', $this->payload([
            'data' => ['message' => [
                'conversation' => null,
                'buttonsResponseMessage' => ['selectedButtonId' => '2'],
            ]],
        ]))->assertOk();

        Queue::assertPushed(ProcessWhatsAppWebhookJob::class, fn ($job) => $job->texto === '2');
    }

    // ─── Filtros ──────────────────────────────────────────────────────────────

    public function test_ignora_eventos_que_nao_sao_messages_upsert(): void
    {
        $this->postJson('/webhook/whatsapp', $this->payload(['event' => 'connection.update']))
            ->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_ignora_mensagens_proprias(): void
    {
        $this->postJson('/webhook/whatsapp', $this->payload([
            'data' => ['key' => ['fromMe' => true]],
        ]))->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_ignora_mensagens_de_grupo(): void
    {
        $this->postJson('/webhook/whatsapp', $this->payload([
            'data' => ['key' => ['remoteJid' => '123456789-987654321@g.us']],
        ]))->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_ignora_texto_que_nao_e_resposta_de_confirmacao(): void
    {
        foreach (['oi', 'bom dia', '11', 'sim quero', '3', ''] as $texto) {
            $this->postJson('/webhook/whatsapp', $this->payload([
                'data' => ['message' => ['conversation' => $texto]],
            ]))->assertOk();
        }

        Queue::assertNothingPushed();
    }

    public function test_ignora_telefone_fora_do_padrao_e164(): void
    {
        foreach (['123@s.whatsapp.net', '1234567890123456789@s.whatsapp.net'] as $jid) {
            $this->postJson('/webhook/whatsapp', $this->payload([
                'data' => ['key' => ['remoteJid' => $jid]],
            ]))->assertOk();
        }

        Queue::assertNothingPushed();
    }

    // ─── Idempotência ─────────────────────────────────────────────────────────

    public function test_mesmo_message_id_reenviado_despacha_o_job_apenas_uma_vez(): void
    {
        $payload = $this->payload(['data' => ['key' => ['id' => 'MSG-RETRY-1']]]);

        $this->postJson('/webhook/whatsapp', $payload)->assertOk();
        $this->postJson('/webhook/whatsapp', $payload)->assertOk();
        $this->postJson('/webhook/whatsapp', $payload)->assertOk();

        Queue::assertPushed(ProcessWhatsAppWebhookJob::class, 1);
    }

    public function test_message_ids_diferentes_despacham_jobs_separados(): void
    {
        $this->postJson('/webhook/whatsapp', $this->payload(['data' => ['key' => ['id' => 'MSG-A']]]))->assertOk();
        $this->postJson('/webhook/whatsapp', $this->payload(['data' => ['key' => ['id' => 'MSG-B']]]))->assertOk();

        Queue::assertPushed(ProcessWhatsAppWebhookJob::class, 2);
    }

    public function test_mensagem_sem_id_ainda_e_processada(): void
    {
        $this->postJson('/webhook/whatsapp', $this->payload(['data' => ['key' => ['id' => null]]]))
            ->assertOk();

        Queue::assertPushed(ProcessWhatsAppWebhookJob::class, 1);
    }

    // ─── Edge cases: payloads malformados nunca podem derrubar o endpoint ────

    public function test_payloads_malformados_sempre_retornam_200_sem_despachar(): void
    {
        $malformados = [
            'vazio' => [],
            'event como array' => ['event' => ['messages.upsert']],
            'event como numero' => ['event' => 123],
            'data como string' => ['event' => 'messages.upsert', 'data' => 'lixo'],
            'key como string' => ['event' => 'messages.upsert', 'data' => ['key' => 'lixo']],
            'remoteJid como array' => [
                'event' => 'messages.upsert',
                'data' => ['key' => ['remoteJid' => ['injetado' => true]]],
            ],
            'message como string' => [
                'event' => 'messages.upsert',
                'data' => [
                    'key' => ['remoteJid' => '5511987654321@s.whatsapp.net'],
                    'message' => 'lixo',
                ],
            ],
            'conversation como array' => [
                'event' => 'messages.upsert',
                'data' => [
                    'key' => ['remoteJid' => '5511987654321@s.whatsapp.net'],
                    'message' => ['conversation' => ['1']],
                ],
            ],
            'fromMe como string' => [
                'event' => 'messages.upsert',
                'data' => ['key' => ['fromMe' => 'sim', 'remoteJid' => '5511987654321@s.whatsapp.net']],
            ],
            'aninhamento profundo inesperado' => [
                'event' => 'messages.upsert',
                'data' => [
                    'key' => ['remoteJid' => '5511987654321@s.whatsapp.net', 'id' => ['x' => 'y']],
                    'message' => ['extendedTextMessage' => 'sem-text'],
                ],
            ],
        ];

        foreach ($malformados as $caso => $payload) {
            $this->postJson('/webhook/whatsapp', $payload)
                ->assertOk()
                ->assertJson(['ok' => true]);
        }

        Queue::assertNothingPushed();
    }

    public function test_corpo_nao_json_retorna_200(): void
    {
        $this->call('POST', '/webhook/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
        ], 'isto não é json')->assertOk();

        Queue::assertNothingPushed();
    }

    // ─── Estresse ─────────────────────────────────────────────────────────────

    public function test_estresse_rajada_de_500_payloads_variados_sem_erro_e_sem_vazamento(): void
    {
        $inicio = microtime(true);
        $memoria = memory_get_usage(true);

        for ($i = 0; $i < 500; $i++) {
            // Alterna entre mensagens irrelevantes (maioria real) e confirmações
            $payload = match ($i % 4) {
                0 => $this->payload(['data' => [
                    'key' => ['id' => "STRESS-{$i}", 'remoteJid' => sprintf('55119%08d@s.whatsapp.net', $i)],
                ]]),
                1 => $this->payload(['data' => ['message' => ['conversation' => "mensagem irrelevante {$i}"]]]),
                2 => $this->payload(['event' => 'connection.update']),
                3 => ['event' => 'messages.upsert', 'data' => ['key' => 'malformado']],
            };

            $this->postJson('/webhook/whatsapp', $payload)->assertOk();
        }

        $duracao = microtime(true) - $inicio;
        $crescimento = memory_get_usage(true) - $memoria;

        // 500 requisições completas pelo kernel devem terminar em tempo razoável
        $this->assertLessThan(60, $duracao, sprintf('500 requisições levaram %.1fs', $duracao));
        // e sem reter memória de payloads processados (limite generoso p/ overhead do framework)
        $this->assertLessThan(64 * 1024 * 1024, $crescimento, 'Crescimento de memória acima de 64MB');

        // Só os 125 payloads de confirmação (i % 4 === 0) geram job
        Queue::assertPushed(ProcessWhatsAppWebhookJob::class, 125);
    }
}
