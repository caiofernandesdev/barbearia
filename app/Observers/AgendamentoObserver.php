<?php

namespace App\Observers;

use App\Models\Agendamento;
use App\Models\ConfiguracaoBarbearia;
use App\Services\WhatsAppService;

class AgendamentoObserver
{
    public function __construct(private WhatsAppService $whatsapp) {}

    // Dispara quando o cliente finaliza o agendamento
    public function created(Agendamento $agendamento): void
    {
        $nomeBarbearia = ConfiguracaoBarbearia::getInstance()->nome_barbearia;

        $this->whatsapp->enviarTexto(
            $agendamento->cliente_telefone,
            static::mensagemRecebido($agendamento, $nomeBarbearia)
        );
    }

    // Dispara quando o admin muda o status
    public function updated(Agendamento $agendamento): void
    {
        if (!$agendamento->wasChanged('status')) return;

        $nomeBarbearia = ConfiguracaoBarbearia::getInstance()->nome_barbearia;

        match ($agendamento->status) {
            'confirmado' => $this->whatsapp->enviarTexto(
                $agendamento->cliente_telefone,
                static::mensagemConfirmado($agendamento, $nomeBarbearia)
            ),
            'cancelado' => $this->whatsapp->enviarTexto(
                $agendamento->cliente_telefone,
                static::mensagemCancelado($agendamento, $nomeBarbearia)
            ),
            default => null,
        };
    }

    // ─── Templates (public static — usados também por actions do painel) ──────

    public static function mensagemRecebido(Agendamento $agendamento, string $nomeBarbearia): string
    {
        $data = $agendamento->data_hora->format('d/m/Y');
        $hora = $agendamento->data_hora->format('H:i');

        return implode("\n", [
            "Olá, {$agendamento->cliente_nome}! 👋",
            "",
            "Recebemos seu agendamento na *{$nomeBarbearia}*!",
            "",
            "📅 *Data:* {$data}",
            "⏰ *Hora:* {$hora}",
            "👨 *Profissional:* {$agendamento->profissional->nome}",
            "💈 *Serviço:* {$agendamento->servico->nome}",
            "",
            "Aguarde nossa confirmação em breve. 😊",
        ]);
    }

    public static function mensagemConfirmado(Agendamento $agendamento, string $nomeBarbearia): string
    {
        $data  = $agendamento->data_hora->format('d/m/Y');
        $hora  = $agendamento->data_hora->format('H:i');
        $preco = 'R$ ' . number_format($agendamento->servico->preco, 2, ',', '.');

        return implode("\n", [
            "Olá, {$agendamento->cliente_nome}! ✂️",
            "",
            "Seu agendamento na *{$nomeBarbearia}* foi *confirmado*!",
            "",
            "📅 *Data:* {$data}",
            "⏰ *Hora:* {$hora}",
            "👨 *Profissional:* {$agendamento->profissional->nome}",
            "💈 *Serviço:* {$agendamento->servico->nome}",
            "💰 *Valor:* {$preco}",
            "",
            "Te esperamos lá! 💈",
        ]);
    }

    public static function mensagemCancelado(Agendamento $agendamento, string $nomeBarbearia): string
    {
        $data = $agendamento->data_hora->format('d/m/Y');
        $hora = $agendamento->data_hora->format('H:i');

        return implode("\n", [
            "Olá, {$agendamento->cliente_nome}! 😔",
            "",
            "Seu agendamento na *{$nomeBarbearia}* foi *cancelado*.",
            "",
            "📅 *Data:* {$data}",
            "⏰ *Hora:* {$hora}",
            "👨 *Profissional:* {$agendamento->profissional->nome}",
            "💈 *Serviço:* {$agendamento->servico->nome}",
            "",
            "Se quiser remarcar, acesse nosso link de agendamento. 😊",
        ]);
    }

    // Chamado pelo command de lembretes (D-1) — pendente: pede confirmação
    public static function mensagemLembrete(Agendamento $agendamento, string $nomeBarbearia): string
    {
        $data = $agendamento->data_hora->format('d/m/Y');
        $hora = $agendamento->data_hora->format('H:i');

        return implode("\n", [
            "Olá, {$agendamento->cliente_nome}! 👋",
            "",
            "Lembrete: seu agendamento na *{$nomeBarbearia}* é *amanhã*!",
            "",
            "📅 *Data:* {$data}",
            "⏰ *Hora:* {$hora}",
            "👨 *Profissional:* {$agendamento->profissional->nome}",
            "",
            "Responda *1* para confirmar ✅",
            "Responda *2* para cancelar ❌",
        ]);
    }

    // Chamado pelo command de lembretes (D-1) — já confirmado: só lembra
    public static function mensagemLembreteConfirmado(Agendamento $agendamento, string $nomeBarbearia): string
    {
        $data = $agendamento->data_hora->format('d/m/Y');
        $hora = $agendamento->data_hora->format('H:i');

        return implode("\n", [
            "Olá, {$agendamento->cliente_nome}! 👋",
            "",
            "Lembrete: seu agendamento na *{$nomeBarbearia}* é *amanhã* e já está confirmado! ✅",
            "",
            "📅 *Data:* {$data}",
            "⏰ *Hora:* {$hora}",
            "👨 *Profissional:* {$agendamento->profissional->nome}",
            "",
            "Te esperamos lá! 💈",
        ]);
    }
}
