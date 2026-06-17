<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\ConfiguracaoBarbearia;
use App\Observers\AgendamentoObserver;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function __construct(private WhatsAppService $whatsapp) {}

    public function handle(Request $request): \Illuminate\Http\JsonResponse
    {
        $payload = $request->all();

        $event = strtolower(str_replace('_', '.', $payload['event'] ?? ''));

        // Evolution API envia "messages.upsert" ou "MESSAGES_UPSERT"
        if ($event !== 'messages.upsert') {
            return response()->json(['ok' => true]);
        }

        $data = $payload['data'] ?? [];
        $key  = $data['key'] ?? [];

        // Ignora mensagens enviadas por nós ou de grupos (@g.us)
        $remoteJid = $key['remoteJid'] ?? '';
        if (($key['fromMe'] ?? false) || str_ends_with($remoteJid, '@g.us')) {
            return response()->json(['ok' => true]);
        }

        // Extrai número do remoteJid: "5511999999999@s.whatsapp.net" → "5511999999999"
        $phone = $this->normalizarTelefone($remoteJid);

        // Texto da mensagem: pode vir em conversation, extendedTextMessage ou buttonsResponseMessage
        $message = $data['message'] ?? [];
        $texto   = trim(strtolower(
            $message['conversation']
            ?? $message['extendedTextMessage']['text']
            ?? $message['buttonsResponseMessage']['selectedButtonId']
            ?? ''
        ));

        Log::info("WhatsApp webhook recebido", compact('phone', 'texto'));

        if ($phone === '' || !in_array($texto, ['1', '2', 'sim', 'nao', 'não'])) {
            return response()->json(['ok' => true]);
        }

        // Busca o agendamento pendente mais próximo para este telefone
        // Aceita agendamentos de hoje em diante (não só no futuro exato)
        $agendamento = Agendamento::where('status', 'pendente')
            ->whereDate('data_hora', '>=', now()->toDateString())
            ->where(function ($q) use ($phone) {
                // Tenta com DDI 55 e sem (cliente pode ter salvo sem o código do país)
                $q->where('cliente_telefone', $phone)
                  ->orWhere('cliente_telefone', ltrim($phone, '55'))
                  ->orWhere('cliente_telefone', preg_replace('/^55/', '', $phone, 1));
            })
            ->with(['profissional', 'servico'])
            ->orderBy('data_hora')
            ->first();

        if (!$agendamento) {
            Log::info("WhatsApp webhook: nenhum agendamento pendente para {$phone}");
            return response()->json(['ok' => true]);
        }

        $nomeBarbearia = ConfiguracaoBarbearia::getInstance()->nome_barbearia;

        if (in_array($texto, ['1', 'sim'])) {
            // Atualiza status → Observer dispara e envia mensagemConfirmado automaticamente
            $agendamento->update(['status' => 'confirmado']);
            Log::info("WhatsApp webhook: agendamento #{$agendamento->id} confirmado pelo cliente.");
        } else {
            // Atualiza status → Observer dispara e envia mensagemCancelado automaticamente
            $agendamento->update(['status' => 'cancelado']);
            Log::info("WhatsApp webhook: agendamento #{$agendamento->id} cancelado pelo cliente.");
        }

        return response()->json(['ok' => true]);
    }

    // Extrai apenas os dígitos do remoteJid da Evolution API
    // Ex: "5511999999999@s.whatsapp.net" → "5511999999999"
    private function normalizarTelefone(string $remoteJid): string
    {
        $semSufixo = preg_replace('/@.*$/', '', $remoteJid);
        return preg_replace('/\D/', '', $semSufixo);
    }
}
