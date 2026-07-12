<?php

namespace App\Console\Commands;

use App\Jobs\EnviarWhatsAppJob;
use App\Models\Agendamento;
use App\Models\ConfiguracaoBarbearia;
use App\Models\Tenant;
use App\Observers\AgendamentoObserver;
use Illuminate\Console\Command;

class EnviarLembretesAgendamentos extends Command
{
    protected $signature = 'agendamentos:lembretes';

    protected $description = 'Envia lembrete por WhatsApp para agendamentos pendentes';

    public function handle(): int
    {
        // Só tenants com o módulo WhatsApp ligado — sem ele não há lembrete/confirmação
        $tenants = Tenant::where('ativo', true)->where('whatsapp_ativo', true)->get();
        $total = 0;

        foreach ($tenants as $tenant) {
            app()->instance('current_tenant', $tenant);

            $config = ConfiguracaoBarbearia::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)->first();

            if (! $config) {
                continue;
            }

            $nomeBarbearia = $config->nome_barbearia;
            $diasAntes = $config->dias_antecedencia_lembrete ?? 1;
            $dataAlvo = now()->addDays($diasAntes);

            $agendamentos = Agendamento::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->whereIn('status', ['pendente', 'confirmado'])
                ->whereDate('data_hora', $dataAlvo->toDateString())
                ->with(['profissional', 'servico'])
                ->get();

            foreach ($agendamentos as $ag) {
                $mensagem = $ag->status === 'confirmado'
                    ? AgendamentoObserver::mensagemLembreteConfirmado($ag, $nomeBarbearia)
                    : AgendamentoObserver::mensagemLembrete($ag, $nomeBarbearia);

                EnviarWhatsAppJob::dispatch($ag->cliente_telefone, $mensagem, $tenant->id);
                $total++;
            }
        }

        $this->info("Lembretes na fila: {$total}");

        return self::SUCCESS;
    }
}
