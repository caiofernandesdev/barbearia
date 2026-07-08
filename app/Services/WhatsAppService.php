<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private function resolveConfig(): array
    {
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;

        if ($tenant && $tenant->whatsapp_config) {
            return [
                'url'      => rtrim($tenant->whatsappBaseUrl(), '/'),
                'apiKey'   => $tenant->whatsappApiKey(),
                'instance' => $tenant->whatsappInstance(),
            ];
        }

        return [
            'url'      => rtrim(config('services.evolution.url', ''), '/'),
            'apiKey'   => config('services.evolution.apikey', ''),
            'instance' => config('services.evolution.instance', ''),
        ];
    }

    public function enabled(): bool
    {
        $cfg = $this->resolveConfig();
        return $cfg['url'] !== '' && $cfg['apiKey'] !== '' && $cfg['instance'] !== '';
    }

    public function enviarTexto(string $telefone, string $mensagem): bool
    {
        $cfg = $this->resolveConfig();

        if ($cfg['url'] === '' || $cfg['apiKey'] === '' || $cfg['instance'] === '') {
            Log::info('WhatsApp desativado — variáveis EVOLUTION não configuradas.');
            return false;
        }

        $numero = $this->formatarNumero($telefone);

        try {
            $endpoint = "{$cfg['url']}/message/sendText/{$cfg['instance']}";

            $response = Http::withoutVerifying()
                ->withHeaders(['apikey' => $cfg['apiKey']])
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

    private function formatarNumero(string $telefone): string
    {
        $digits = preg_replace('/\D/', '', $telefone);

        if (str_starts_with($digits, '55') && strlen($digits) >= 12) {
            return $digits;
        }

        return '55' . $digits;
    }
}
