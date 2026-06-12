<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private string $url;
    private string $apiKey;
    private string $instance;

    public function __construct()
    {
        $this->url      = rtrim(config('services.evolution.url', ''), '/');
        $this->apiKey   = config('services.evolution.apikey', '');
        $this->instance = config('services.evolution.instance', '');
    }

    public function enabled(): bool
    {
        return $this->url !== '' && $this->apiKey !== '' && $this->instance !== '';
    }

    public function enviarTexto(string $telefone, string $mensagem): bool
    {
        if (!$this->enabled()) {
            Log::info('WhatsApp desativado — variáveis EVOLUTION não configuradas.');
            return false;
        }

        $numero = $this->formatarNumero($telefone);

        try {
            $endpoint = "{$this->url}/message/sendText/{$this->instance}";

            $response = Http::withoutVerifying()
                ->withHeaders(['apikey' => $this->apiKey])
                ->post($endpoint, [
                    'number' => $numero,
                    'text'   => $mensagem,
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
            Log::error('WhatsApp: erro ao chamar Evolution API.', [
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
