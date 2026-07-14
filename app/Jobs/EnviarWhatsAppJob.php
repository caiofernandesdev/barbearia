<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EnviarWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Rate-limiting anti-ban: intervalo mínimo entre mensagens do mesmo número
    // (envio espaçado parece humano, não rajada de bot) e teto diário por número.
    private const INTERVALO_SEGUNDOS = 8;

    private const LIMITE_DIARIO = 250;

    public int $tries = 8; // aumenta as tentativas por causa dos releases do rate-limit

    public int $backoff = 10;

    public function __construct(
        private string $telefone,
        private string $mensagem,
        private ?int $tenantId = null,
    ) {}

    public function handle(): void
    {
        $tenant = null;
        if ($this->tenantId) {
            $tenant = Tenant::find($this->tenantId);
            if ($tenant) {
                app()->instance('current_tenant', $tenant);

                // Módulo WhatsApp desligado para este tenant: não envia nada
                if (! $tenant->whatsappAtivo()) {
                    Log::info("WhatsApp inativo no tenant {$tenant->id} — job ignorado");

                    return;
                }
            }
        }

        $whatsapp = new WhatsAppService;
        if (! $whatsapp->enabled()) {
            Log::info("WhatsApp desativado — job ignorado para {$this->telefone}");

            return;
        }

        $chaveTenant = $tenant?->id ?? 'global';

        // Teto diário: acima disso, para de enviar no dia (evita padrão de disparo em massa)
        $chaveDia = "wa-count:{$chaveTenant}:".now()->toDateString();
        if ((int) Cache::get($chaveDia, 0) >= self::LIMITE_DIARIO) {
            Log::warning("WhatsApp: limite diário atingido no tenant {$chaveTenant} — mensagem descartada");

            return;
        }

        // Espaçamento: no mínimo INTERVALO_SEGUNDOS entre mensagens do mesmo número.
        // Se veio cedo demais, devolve o job à fila com atraso (não bloqueia o worker).
        $chaveRitmo = "wa-ritmo:{$chaveTenant}";
        $ultimo = (float) Cache::get($chaveRitmo, 0);
        $agora = microtime(true);
        $decorrido = $agora - $ultimo;

        if ($ultimo > 0 && $decorrido < self::INTERVALO_SEGUNDOS) {
            $this->release((int) ceil(self::INTERVALO_SEGUNDOS - $decorrido) + rand(0, 3));

            return;
        }

        Cache::put($chaveRitmo, $agora, now()->addMinutes(10));
        Cache::put($chaveDia, (int) Cache::get($chaveDia, 0) + 1, now()->endOfDay());

        $whatsapp->enviarTexto($this->telefone, $this->mensagem);
    }
}
