<?php

namespace App\Observers;

use App\Jobs\EnviarWhatsAppJob;
use App\Models\Agendamento;
use App\Models\ConfiguracaoBarbearia;
use App\Models\Tenant;

class AgendamentoObserver
{
    private function ensureTenant(Agendamento $agendamento): void
    {
        if (app()->bound('current_tenant')) {
            return;
        }

        if ($agendamento->tenant_id) {
            $tenant = Tenant::withoutGlobalScopes()->find($agendamento->tenant_id);
            if ($tenant) {
                app()->instance('current_tenant', $tenant);
            }
        }
    }

    private function enviar(string $telefone, string $mensagem, ?int $tenantId): void
    {
        EnviarWhatsAppJob::dispatch($telefone, $mensagem, $tenantId);
    }

    public function created(Agendamento $agendamento): void
    {
        $this->ensureTenant($agendamento);
        $nomeBarbearia = ConfiguracaoBarbearia::getInstance()->nome_barbearia;
        $tid = $agendamento->tenant_id;

        $this->enviar($agendamento->cliente_telefone, static::mensagemRecebido($agendamento, $nomeBarbearia), $tid);
        $this->notificarBarbeiro($agendamento, $nomeBarbearia, 'novo');
    }

    public function updated(Agendamento $agendamento): void
    {
        $this->ensureTenant($agendamento);
        $nomeBarbearia = ConfiguracaoBarbearia::getInstance()->nome_barbearia;
        $tid = $agendamento->tenant_id;

        if ($agendamento->wasChanged('data_hora')) {
            $this->enviar($agendamento->cliente_telefone, static::mensagemReagendado($agendamento, $nomeBarbearia), $tid);
            $this->notificarBarbeiro($agendamento, $nomeBarbearia, 'reagendado');

            return;
        }

        if (! $agendamento->wasChanged('status')) {
            return;
        }

        match ($agendamento->status) {
            'confirmado' => $this->enviar($agendamento->cliente_telefone, static::mensagemConfirmado($agendamento, $nomeBarbearia), $tid),
            'cancelado' => (function () use ($agendamento, $nomeBarbearia, $tid) {
                $this->enviar($agendamento->cliente_telefone, static::mensagemCancelado($agendamento, $nomeBarbearia), $tid);
                $this->notificarBarbeiro($agendamento, $nomeBarbearia, 'cancelado');
            })(),
            default => null,
        };
    }

    private function notificarBarbeiro(Agendamento $agendamento, string $nomeBarbearia, string $tipo): void
    {
        $profissional = $agendamento->profissional;
        if (! $profissional || empty($profissional->telefone)) {
            return;
        }
        if (preg_replace('/\D/', '', $profissional->telefone) === $agendamento->cliente_telefone) {
            return;
        }

        $data = $agendamento->data_hora->format('d/m/Y');
        $hora = $agendamento->data_hora->format('H:i');
        $cliente = $agendamento->cliente_nome;
        $servico = $agendamento->nomesServicos();

        $mensagem = match ($tipo) {
            'novo' => "📋 *Novo agendamento!*\n\n👤 *Cliente:* {$cliente}\n📅 *Data:* {$data}\n⏰ *Hora:* {$hora}\n✂️ *Serviço:* {$servico}\n\nStatus: Pendente.",
            'cancelado' => "❌ *Agendamento cancelado*\n\n👤 *Cliente:* {$cliente}\n📅 *Data:* {$data}\n⏰ *Hora:* {$hora}\n\nO horário foi liberado.",
            'reagendado' => "🔄 *Agendamento remarcado*\n\n👤 *Cliente:* {$cliente}\n📅 *Nova data:* {$data}\n⏰ *Novo horário:* {$hora}\n✂️ *Serviço:* {$servico}",
            default => null,
        };

        if ($mensagem) {
            $this->enviar($profissional->telefone, $mensagem, $agendamento->tenant_id);
        }
    }

    // ─── Templates ───────────────────────────────────────────────────────────

    public static function mensagemRecebido(Agendamento $ag, string $nb): string
    {
        $servicos = $ag->nomesServicos();

        return "Olá, {$ag->cliente_nome}! 👋\n\nRecebemos seu agendamento na *{$nb}*!\n\n📅 *Data:* {$ag->data_hora->format('d/m/Y')}\n⏰ *Hora:* {$ag->data_hora->format('H:i')}\n👨 *Profissional:* {$ag->profissional->nome}\n💈 *Serviço:* {$servicos}\n\nAguarde nossa confirmação. 😊";
    }

    public static function mensagemConfirmado(Agendamento $ag, string $nb): string
    {
        $servicos = $ag->nomesServicos();
        $preco = 'R$ '.number_format((float) ($ag->valor_total ?? $ag->servico?->preco ?? 0), 2, ',', '.');

        return "Olá, {$ag->cliente_nome}! ✂️\n\nSeu agendamento na *{$nb}* foi *confirmado*!\n\n📅 *Data:* {$ag->data_hora->format('d/m/Y')}\n⏰ *Hora:* {$ag->data_hora->format('H:i')}\n👨 *Profissional:* {$ag->profissional->nome}\n💈 *Serviço:* {$servicos}\n💰 *Valor:* {$preco}\n\nTe esperamos lá! 💈";
    }

    public static function mensagemCancelado(Agendamento $ag, string $nb): string
    {
        return "Olá, {$ag->cliente_nome}! 😔\n\nSeu agendamento na *{$nb}* foi *cancelado*.\n\n📅 *Data:* {$ag->data_hora->format('d/m/Y')}\n⏰ *Hora:* {$ag->data_hora->format('H:i')}\n\nSe quiser remarcar, acesse nosso link. 😊";
    }

    public static function mensagemReagendado(Agendamento $ag, string $nb): string
    {
        return "Olá, {$ag->cliente_nome}! 📅\n\nSeu agendamento na *{$nb}* foi *remarcado*:\n\n📅 *Nova data:* {$ag->data_hora->format('d/m/Y')}\n⏰ *Novo horário:* {$ag->data_hora->format('H:i')}\n\nQualquer dúvida, entre em contato. 😊";
    }

    public static function mensagemLembrete(Agendamento $ag, string $nb): string
    {
        return "Olá, {$ag->cliente_nome}! 👋\n\nGostaríamos de confirmar seu agendamento na *{$nb}*:\n\n📅 *Data:* {$ag->data_hora->format('d/m/Y')}\n⏰ *Hora:* {$ag->data_hora->format('H:i')}\n👨 *Profissional:* {$ag->profissional->nome}\n\nResponda *1* para confirmar ✅\nResponda *2* para cancelar ❌";
    }

    public static function mensagemLembreteConfirmado(Agendamento $ag, string $nb): string
    {
        return "Olá, {$ag->cliente_nome}! 👋\n\nLembrete: seu agendamento na *{$nb}* é *amanhã* e já está confirmado! ✅\n\n📅 *Data:* {$ag->data_hora->format('d/m/Y')}\n⏰ *Hora:* {$ag->data_hora->format('H:i')}\n\nTe esperamos lá! 💈";
    }
}
