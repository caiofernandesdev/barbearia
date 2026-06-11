<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private string  $instance;
    private string  $token;
    private ?string $clientToken;

    public function __construct()
    {
        $this->instance    = config('services.zapi.instance', '');
        $this->token       = config('services.zapi.token', '');
        $this->clientToken = config('services.zapi.client_token') ?: null;
    }

    public function enabled(): bool
    {
        return $this->instance !== '' && $this->token !== '';
    }

    public function enviarTexto(string $telefone, string $mensagem): bool
    {
        if (!$this->enabled()) {
            Log::info('WhatsApp desativado — variáveis Z-API não configuradas.');
            return false;
        }

        $numero = $this->formatarNumero($telefone);

        try {
            $url = "https://api.z-api.io/instances/{$this->instance}/token/{$this->token}/send-text";

            // withoutVerifying() contorna problema de CA no Windows em desenvolvimento
            // Em produção (Linux) o cURL já tem os certificados e não precisa disso
            $http = Http::withoutVerifying();

            // Client-Token é exigido quando a instância tem segurança ativada no Z-API
            if ($this->clientToken) {
                $http = $http->withHeaders(['Client-Token' => $this->clientToken]);
            }

            $response = $http->post($url, [
                'phone'   => $numero,
                'message' => $mensagem,
            ]);

            if (!$response->successful()) {
                Log::warning('WhatsApp: falha ao enviar mensagem.', [
                    'numero' => $numero,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('WhatsApp: erro ao chamar Z-API.', [
                'numero' => $numero,
                'erro'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    // Garante formato 55DDD9XXXXXXXX para números brasileiros
    private function formatarNumero(string $telefone): string
    {
        $digits = preg_replace('/\D/', '', $telefone);

        if (str_starts_with($digits, '55') && strlen($digits) >= 12) {
            return $digits;
        }

        return '55' . $digits;
    }
}
