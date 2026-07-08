<?php

namespace Tests\Feature;

use App\Jobs\ProcessWhatsAppWebhookJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Testes de segurança do webhook: autenticação por token, injeções (SQLi, XSS,
 * log injection, path traversal), payloads hostis e abuso de lógica de negócio.
 * Nenhum teste toca o banco — Queue::fake intercepta qualquer dispatch.
 */
class WhatsAppWebhookSecurityTest extends TestCase
{
    private const TOKEN = 'tok-secreto-de-teste-a1b2c3';

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function payloadConfirmacao(array $overrides = []): array
    {
        return array_replace_recursive([
            'event' => 'messages.upsert',
            'data' => [
                'key' => [
                    'id' => 'MSG-'.uniqid('', true),
                    'remoteJid' => '5511987654321@s.whatsapp.net',
                    'fromMe' => false,
                ],
                'message' => ['conversation' => '1'],
            ],
        ], $overrides);
    }

    // ─── Autenticação por token (anti-spoofing) ──────────────────────────────

    public function test_com_token_configurado_exige_token_correto_na_url(): void
    {
        config(['services.evolution.webhook_token' => self::TOKEN]);

        $this->postJson('/webhook/whatsapp/'.self::TOKEN, $this->payloadConfirmacao())
            ->assertOk();

        Queue::assertPushed(ProcessWhatsAppWebhookJob::class, 1);
    }

    public function test_token_errado_recebe_401_e_nada_e_processado(): void
    {
        config(['services.evolution.webhook_token' => self::TOKEN]);

        $this->postJson('/webhook/whatsapp/token-forjado', $this->payloadConfirmacao())
            ->assertStatus(401);

        Queue::assertNothingPushed();
    }

    public function test_sem_token_na_url_recebe_401_quando_token_esta_configurado(): void
    {
        config(['services.evolution.webhook_token' => self::TOKEN]);

        $this->postJson('/webhook/whatsapp', $this->payloadConfirmacao())
            ->assertStatus(401);

        Queue::assertNothingPushed();
    }

    public function test_producao_sem_token_configurado_rejeita_tudo_fail_closed(): void
    {
        config(['services.evolution.webhook_token' => null]);
        $this->app['env'] = 'production';

        try {
            $this->postJson('/webhook/whatsapp', $this->payloadConfirmacao())
                ->assertStatus(401);
        } finally {
            $this->app['env'] = 'testing';
        }

        Queue::assertNothingPushed();
    }

    public function test_resposta_de_token_invalido_nao_vaza_informacao(): void
    {
        config(['services.evolution.webhook_token' => self::TOKEN]);

        $response = $this->postJson('/webhook/whatsapp/errado', $this->payloadConfirmacao());

        $response->assertStatus(401)->assertExactJson(['ok' => false]);
    }

    // ─── Injeção: SQLi, XSS, log injection, path traversal ──────────────────

    public function test_sql_injection_no_remote_jid_e_neutralizada(): void
    {
        $ataques = [
            "5511987654321' OR '1'='1@s.whatsapp.net",
            "5511987654321'; DROP TABLE agendamentos;--@s.whatsapp.net",
            '5511987654321 UNION SELECT * FROM users@s.whatsapp.net',
        ];

        foreach ($ataques as $jid) {
            $this->postJson('/webhook/whatsapp', $this->payloadConfirmacao([
                'data' => ['key' => ['remoteJid' => $jid]],
            ]))->assertOk();
        }

        // O que sobrar após sanitização deve ser SÓ dígitos — nunca o payload bruto
        Queue::assertPushed(ProcessWhatsAppWebhookJob::class, function ($job) {
            return preg_match('/^\d{8,15}$/', $job->phone) === 1;
        });
    }

    public function test_sql_injection_sem_digitos_suficientes_e_descartada(): void
    {
        $this->postJson('/webhook/whatsapp', $this->payloadConfirmacao([
            'data' => ['key' => ['remoteJid' => "' OR 1=1;--@s.whatsapp.net"]],
        ]))->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_xss_e_html_no_texto_nao_sao_aceitos(): void
    {
        $ataques = [
            '<script>alert(1)</script>',
            '<img src=x onerror=alert(1)>',
            'javascript:alert(1)',
            '1<script>',
        ];

        foreach ($ataques as $texto) {
            $this->postJson('/webhook/whatsapp', $this->payloadConfirmacao([
                'data' => ['message' => ['conversation' => $texto]],
            ]))->assertOk();
        }

        Queue::assertNothingPushed();
    }

    public function test_log_injection_com_quebra_de_linha_no_texto_e_rejeitada(): void
    {
        $this->postJson('/webhook/whatsapp', $this->payloadConfirmacao([
            'data' => ['message' => ['conversation' => "1\n[ALERT] log forjado"]],
        ]))->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_path_traversal_no_message_id_nao_quebra_a_deduplicacao(): void
    {
        $payload = $this->payloadConfirmacao([
            'data' => ['key' => ['id' => '../../../etc/passwd']],
        ]);

        $this->postJson('/webhook/whatsapp', $payload)->assertOk();
        $this->postJson('/webhook/whatsapp', $payload)->assertOk();

        // id malicioso é hasheado: dedup continua funcionando e nada vaza pro filesystem
        Queue::assertPushed(ProcessWhatsAppWebhookJob::class, 1);
    }

    public function test_null_bytes_e_caracteres_de_controle_nunca_chegam_ao_job(): void
    {
        // trim() remove \x00 das bordas e preg_replace(\D) remove do meio:
        // o input normaliza para valores limpos — controle nunca propaga ao job
        $this->postJson('/webhook/whatsapp', $this->payloadConfirmacao([
            'data' => [
                'key' => ['remoteJid' => "55119\x0087654321@s.whatsapp.net"],
                'message' => ['conversation' => "\x001"],
            ],
        ]))->assertOk();

        Queue::assertPushed(ProcessWhatsAppWebhookJob::class, function ($job) {
            return $job->phone === '5511987654321'
                && $job->texto === '1'
                && ! str_contains($job->phone."\0".$job->texto, "\x00\x00");
        });

        // Null byte NO MEIO do texto não é trimável: precisa ser rejeitado
        Queue::fake();
        $this->postJson('/webhook/whatsapp', $this->payloadConfirmacao([
            'data' => ['message' => ['conversation' => "1\x002"]],
        ]))->assertOk();

        Queue::assertNothingPushed();
    }

    // ─── Bypass de whitelist com unicode ─────────────────────────────────────

    public function test_digitos_unicode_nao_passam_pela_whitelist(): void
    {
        // U+0661 (١ arábico), U+FF11 (１ fullwidth) — parecem "1" mas não são
        foreach (["\u{0661}", "\u{FF11}", '¹'] as $falsoUm) {
            $this->postJson('/webhook/whatsapp', $this->payloadConfirmacao([
                'data' => ['message' => ['conversation' => $falsoUm]],
            ]))->assertOk();
        }

        Queue::assertNothingPushed();
    }

    // ─── DoS: payloads gigantes e profundamente aninhados ───────────────────

    public function test_payload_gigante_nao_derruba_o_endpoint(): void
    {
        $this->postJson('/webhook/whatsapp', $this->payloadConfirmacao([
            'data' => ['message' => ['conversation' => str_repeat('A', 1024 * 1024)]],
        ]))->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_aninhamento_profundo_nao_derruba_o_endpoint(): void
    {
        $profundo = 'fundo';
        for ($i = 0; $i < 100; $i++) {
            $profundo = ['nivel' => $profundo];
        }

        $this->postJson('/webhook/whatsapp', [
            'event' => 'messages.upsert',
            'data' => $profundo,
        ])->assertOk();

        Queue::assertNothingPushed();
    }

    // ─── Abuso de lógica de negócio ──────────────────────────────────────────

    public function test_texto_de_confirmacao_com_conteudo_extra_nao_e_aceito(): void
    {
        // Whitelist estrita: só a resposta exata confirma/cancela
        foreach (['1 e 2', 'sim, cancela tudo', '2;2;2', '1 OR 2'] as $texto) {
            $this->postJson('/webhook/whatsapp', $this->payloadConfirmacao([
                'data' => ['message' => ['conversation' => $texto]],
            ]))->assertOk();
        }

        Queue::assertNothingPushed();
    }

    public function test_replay_do_mesmo_evento_nao_duplica_processamento(): void
    {
        $payload = $this->payloadConfirmacao([
            'data' => ['key' => ['id' => 'MSG-REPLAY-ATTACK']],
        ]);

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/webhook/whatsapp', $payload)->assertOk();
        }

        Queue::assertPushed(ProcessWhatsAppWebhookJob::class, 1);
    }
}
