<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EnviarWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        private string $telefone,
        private string $mensagem,
        private ?int $tenantId = null,
    ) {}

    public function handle(): void
    {
        if ($this->tenantId) {
            $tenant = Tenant::find($this->tenantId);
            if ($tenant) {
                app()->instance('current_tenant', $tenant);
            }
        }

        $whatsapp = new WhatsAppService();
        if (! $whatsapp->enabled()) {
            Log::info("WhatsApp desativado — job ignorado para {$this->telefone}");
            return;
        }

        $whatsapp->enviarTexto($this->telefone, $this->mensagem);
    }
}
