<?php

namespace App\Jobs;

use App\Models\Agendamento;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessWhatsAppWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Fonte única das respostas aceitas — o controller usa para filtrar o webhook. */
    public const RESPOSTAS_VALIDAS = ['1', '2', 'sim', 'nao', 'não'];

    private const CONFIRMAR = ['1', 'sim'];

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(
        public readonly string $phone,
        public readonly string $texto,
    ) {}

    public function handle(): void
    {
        if (! in_array($this->texto, self::RESPOSTAS_VALIDAS, true)) {
            return;
        }

        // Worker de fila é processo de longa duração: descarta tenant de job anterior
        // para o TenantScope não filtrar as relações com o tenant errado
        app()->forgetInstance('current_tenant');

        // Eager load obrigatório: o AgendamentoObserver monta as mensagens de
        // confirmação/cancelamento com profissional e servico
        $agendamento = Agendamento::withoutGlobalScopes()
            ->where('status', 'pendente')
            ->whereDate('data_hora', '>=', now()->toDateString())
            ->whereIn('cliente_telefone', $this->candidatosTelefone())
            ->with(['profissional', 'servico'])
            ->orderBy('data_hora')
            ->first();

        if (! $agendamento) {
            Log::info("WhatsApp webhook: nenhum agendamento pendente para {$this->telefoneMascarado()}");

            return;
        }

        // O observer precisa do tenant para ler a configuração da barbearia
        if ($agendamento->tenant_id) {
            $tenant = Tenant::withoutGlobalScopes()->find($agendamento->tenant_id);
            if ($tenant) {
                app()->instance('current_tenant', $tenant);
            }
        }

        $novoStatus = in_array($this->texto, self::CONFIRMAR, true) ? 'confirmado' : 'cancelado';

        // O UPDATE persiste antes de o observer disparar; se a notificação falhar
        // (relação apagada, template quebrado), a resposta do cliente não é perdida
        try {
            $agendamento->update(['status' => $novoStatus]);
            Log::info("WhatsApp webhook: agendamento #{$agendamento->id} {$novoStatus} pelo cliente.");
        } catch (Throwable $e) {
            Log::error("WhatsApp webhook: falha ao notificar agendamento #{$agendamento->id}", [
                'erro' => $e->getMessage(),
                'status_aplicado' => Agendamento::withoutGlobalScopes()
                    ->whereKey($agendamento->id)->value('status'),
            ]);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error("WhatsApp webhook job falhou para {$this->telefoneMascarado()}", ['erro' => $e->getMessage()]);
    }

    /** Telefone é PII (LGPD) — nos logs, só os 4 últimos dígitos. */
    private function telefoneMascarado(): string
    {
        return str_repeat('*', max(strlen($this->phone) - 4, 0)).substr($this->phone, -4);
    }

    /**
     * Variações do número que podem estar salvas no agendamento.
     *
     * O JID do WhatsApp vem com DDI 55 e, em números antigos, SEM o 9º dígito;
     * o cliente digita o número no site geralmente sem DDI e COM o 9º dígito.
     */
    private function candidatosTelefone(): array
    {
        $candidatos = [$this->phone];

        // Remove o DDI 55 apenas do início (ltrim($phone, '55') comeria 5s do número)
        $local = preg_replace('/^55/', '', $this->phone, 1);
        $candidatos[] = $local;

        if (strlen($local) === 10) {
            // JID sem o 9º dígito → insere o 9 depois do DDD
            $candidatos[] = substr($local, 0, 2).'9'.substr($local, 2);
        } elseif (strlen($local) === 11 && $local[2] === '9') {
            // JID com o 9º dígito → versão sem o 9
            $candidatos[] = substr($local, 0, 2).substr($local, 3);
        }

        return array_values(array_unique(array_filter($candidatos)));
    }
}
