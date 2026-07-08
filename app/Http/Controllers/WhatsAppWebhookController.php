<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsAppWebhookJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppWebhookController extends Controller
{
    // Janela de deduplicação: a Evolution API reenvia o mesmo evento em retries/reconexões
    private const DEDUP_TTL_HORAS = 6;

    public function handle(Request $request, ?string $token = null): JsonResponse
    {
        // Autenticação por segredo compartilhado na URL — sem isso, qualquer um
        // que descubra o endpoint pode forjar confirmações/cancelamentos
        if (! $this->tokenValido($token)) {
            return response()->json(['ok' => false], 401);
        }

        // Autenticado, o webhook responde 200 SEMPRE — qualquer outro status faz a
        // Evolution API reenfileirar o evento e gerar tempestade de retries.
        try {
            $this->processar($request->all());
        } catch (Throwable $e) {
            Log::warning('WhatsApp webhook: payload descartado por erro', [
                'erro' => $e->getMessage(),
                'origem' => $e->getFile().':'.$e->getLine(),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    private function processar(array $payload): void
    {
        $event = self::str($payload['event'] ?? null);
        if (strtolower(str_replace('_', '.', $event)) !== 'messages.upsert') {
            return;
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $key = is_array($data['key'] ?? null) ? $data['key'] : [];

        // Ignora mensagens próprias e de grupos
        $remoteJid = self::str($key['remoteJid'] ?? null);
        if (($key['fromMe'] ?? false) || $remoteJid === '' || str_ends_with($remoteJid, '@g.us')) {
            return;
        }

        // E.164: telefones válidos têm entre 8 e 15 dígitos — descarta JIDs corrompidos
        $phone = preg_replace('/\D/', '', strstr($remoteJid, '@', true) ?: $remoteJid);
        $len = strlen($phone);
        if ($len < 8 || $len > 15) {
            return;
        }

        $message = is_array($data['message'] ?? null) ? $data['message'] : [];
        // mb_strtolower: "NÃO" precisa virar "não" (strtolower não trata multibyte)
        $texto = mb_strtolower(trim(self::str(
            $message['conversation']
            ?? $message['extendedTextMessage']['text']
            ?? $message['buttonsResponseMessage']['selectedButtonId']
            ?? null
        )));

        // Filtra ANTES de tocar cache/fila — a Evolution entrega todas as conversas
        // do número, e só respostas de confirmação nos interessam
        if (! in_array($texto, ProcessWhatsAppWebhookJob::RESPOSTAS_VALIDAS, true)) {
            return;
        }

        // Idempotência por message id: retries da Evolution não geram jobs duplicados.
        // sha1 neutraliza ids maliciosos (path traversal, controle) na chave de cache
        $messageId = self::str($key['id'] ?? null);
        if ($messageId !== '' && ! Cache::add(
            'wa-webhook:'.sha1($messageId),
            1,
            now()->addHours(self::DEDUP_TTL_HORAS)
        )) {
            return;
        }

        ProcessWhatsAppWebhookJob::dispatch($phone, $texto);
    }

    /**
     * Valida o segredo compartilhado da URL do webhook.
     *
     * Fail-closed: em produção sem WHATSAPP_WEBHOOK_TOKEN configurado, rejeita tudo.
     * hash_equals evita timing attack na comparação do token.
     */
    private function tokenValido(?string $token): bool
    {
        $esperado = (string) config('services.evolution.webhook_token', '');

        if ($esperado === '') {
            if (app()->isProduction()) {
                Log::critical('WhatsApp webhook: WHATSAPP_WEBHOOK_TOKEN não configurado — requisições rejeitadas');

                return false;
            }

            // Ambiente local/teste sem token configurado: permite para desenvolvimento
            return true;
        }

        return is_string($token) && hash_equals($esperado, $token);
    }

    /** Payloads externos podem trazer array/objeto onde se espera string — nunca confiar no tipo. */
    private static function str(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
