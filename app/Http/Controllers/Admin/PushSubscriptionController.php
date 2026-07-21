<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Services\PushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registro dos aparelhos que recebem push. Sempre no contexto do usuário
 * logado no painel — ninguém inscreve aparelho em nome de outro.
 */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'endpoint' => 'required|string|max:2000',
            'keys.p256dh' => 'required|string|max:255',
            'keys.auth' => 'required|string|max:255',
        ]);

        $user = auth('admin')->user();

        if (! $user) {
            return response()->json(['erro' => 'não autenticado'], 401);
        }

        PushSubscription::registrar(
            $user,
            $dados['endpoint'],
            $dados['keys']['p256dh'],
            $dados['keys']['auth'],
        );

        return response()->json(['ok' => true]);
    }

    /** Dispara um push para o próprio usuário — para ele testar sem depender de um agendamento real */
    public function teste(PushService $push): JsonResponse
    {
        $user = auth('admin')->user();

        if (! $user) {
            return response()->json(['erro' => 'não autenticado'], 401);
        }

        $aparelhos = PushSubscription::where('user_id', $user->id)->count();

        if ($aparelhos === 0) {
            return response()->json(['ok' => false, 'motivo' => 'nenhum aparelho ativado'], 422);
        }

        $push->enviarPara($user, [
            'title' => 'Teste do Atendix',
            'body' => 'Se você está vendo isto, os avisos estão funcionando.',
            'url' => route('filament.admin.pages.agenda-geral'),
        ]);

        return response()->json(['ok' => true, 'aparelhos' => $aparelhos]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $dados = $request->validate(['endpoint' => 'required|string|max:2000']);

        $user = auth('admin')->user();

        if (! $user) {
            return response()->json(['erro' => 'não autenticado'], 401);
        }

        // Só remove aparelho do próprio usuário
        PushSubscription::where('user_id', $user->id)
            ->where('endpoint_hash', hash('sha256', $dados['endpoint']))
            ->delete();

        return response()->json(['ok' => true]);
    }
}
