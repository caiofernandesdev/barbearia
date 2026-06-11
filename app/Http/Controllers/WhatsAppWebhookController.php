<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\ConfiguracaoBarbearia;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function __construct(private WhatsAppService $whatsapp) {}

    public function handle(Request $request): \Illuminate\Http\JsonResponse
    {
        // Ignora mensagens enviadas pelo próprio número (fromMe) ou de grupos
        if ($request->boolean('fromMe') || $request->boolean('isGroupMsg')) {
            return response()->json(['ok' => true]);
        }

        $phone = $this->normalizarTelefone($request->input('phone', ''));
        $texto = trim(strtolower($request->input('text.message', '')));

        if ($phone === '' || !in_array($texto, ['1', '2', 'sim', 'nao', 'não'])) {
            return response()->json(['ok' => true]);
        }

        // Busca o agendamento pendente mais próximo para este telefone
        $agendamento = Agendamento::where('status', 'pendente')
            ->where('data_hora', '>', now())
            ->where(function ($q) use ($phone) {
                // Tenta com código do país e sem (remove exatamente 2 dígitos '55' do início)
                $q->where('cliente_telefone', $phone)
                  ->orWhere('cliente_telefone', substr($phone, 2));
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
            $agendamento->update(['status' => 'confirmado']);
            // O observer dispara e envia a mensagem de confirmação automaticamente
        } else {
            $agendamento->update(['status' => 'cancelado']);
            $this->whatsapp->enviarTexto(
                $agendamento->cliente_telefone,
                "Ok, {$agendamento->cliente_nome}. Seu agendamento foi *cancelado* conforme solicitado.\n\nPara reagendar acesse: " . url('/')
            );
        }

        return response()->json(['ok' => true]);
    }

    // Remove código do país e sufixo @c.us para comparar com o BD
    private function normalizarTelefone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        // Remove @c.us já foi tratado pelo preg_replace acima
        return $digits;
    }
}