<?php

namespace App\Console\Commands;

use App\Jobs\EnviarWhatsAppJob;
use App\Models\Agendamento;
use App\Models\ConfiguracaoBarbearia;
use App\Models\Tenant;
use Illuminate\Console\Command;

class CancelarNaoConfirmados extends Command
{
    protected $signature = 'agendamentos:cancelar-nao-confirmados';

    protected $description = 'Cancela agendamentos pendentes que não foram confirmados dentro do prazo configurado';

    public function handle(): int
    {
        // Sem WhatsApp os agendamentos já nascem confirmados — nada a cancelar por falta de confirmação
        $tenants = Tenant::where('ativo', true)->where('whatsapp_ativo', true)->get();
        $totalCancelados = 0;

        foreach ($tenants as $tenant) {
            app()->instance('current_tenant', $tenant);

            $config = ConfiguracaoBarbearia::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->first();

            if (! $config || ! $config->cancelar_nao_confirmados) {
                continue;
            }

            $horasAntecedencia = $config->horas_antecedencia_cancelamento ?? 2;
            $limite = now()->addHours($horasAntecedencia);

            $pendentes = Agendamento::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('status', 'pendente')
                ->where('data_hora', '<=', $limite)
                ->where('data_hora', '>=', now())
                ->with(['profissional', 'servico'])
                ->get();

            if ($pendentes->isEmpty()) {
                continue;
            }

            $nomeBarbearia = $config->nome_barbearia;

            foreach ($pendentes as $ag) {
                $ag->updateQuietly(['status' => 'cancelado']);

                $data = $ag->data_hora->format('d/m/Y');
                $hora = $ag->data_hora->format('H:i');
                $profNome = $ag->profissional->nome ?? '';
                $servNome = $ag->servico->nome ?? '';

                EnviarWhatsAppJob::dispatch($ag->cliente_telefone, "Olá, {$ag->cliente_nome}! 😔\n\nSeu agendamento na *{$nomeBarbearia}* foi cancelado por falta de confirmação.\n\n📅 *Data:* {$data}\n⏰ *Hora:* {$hora}\n👨 *Profissional:* {$profNome}\n\nSe quiser remarcar, acesse nosso link. 😊", $tenant->id);

                if ($ag->profissional && $ag->profissional->telefone) {
                    EnviarWhatsAppJob::dispatch($ag->profissional->telefone, "⚠️ *Horário liberado*\n\nCliente *{$ag->cliente_nome}* não confirmou.\n\n📅 *Data:* {$data}\n⏰ *Hora:* {$hora}\n✂️ *Serviço:* {$servNome}\n\nHorário livre.", $tenant->id);
                }

                $totalCancelados++;
                $this->line("  Cancelado: #{$ag->id} {$ag->cliente_nome} — {$ag->data_hora->format('d/m H:i')} [{$tenant->slug}]");
            }
        }

        $this->info("Total cancelados: {$totalCancelados}");

        return self::SUCCESS;
    }
}
