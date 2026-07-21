<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Envio de notificação push para os aparelhos de um usuário.
 *
 * Sem chaves VAPID configuradas o serviço fica inativo e nada é enviado —
 * o resto do sistema segue funcionando (o sininho não depende disto).
 */
class PushService
{
    public function configurado(): bool
    {
        return filled(config('webpush.vapid.public_key'))
            && filled(config('webpush.vapid.private_key'));
    }

    /**
     * @param  array<string, mixed>  $payload  title, body, url
     */
    public function enviarPara(User $user, array $payload): void
    {
        if (! $this->configurado()) {
            return;
        }

        $inscricoes = PushSubscription::where('user_id', $user->id)->get();

        if ($inscricoes->isEmpty()) {
            return;
        }

        try {
            $webPush = new WebPush(['VAPID' => [
                'subject' => config('webpush.vapid.subject'),
                'publicKey' => config('webpush.vapid.public_key'),
                'privateKey' => config('webpush.vapid.private_key'),
            ]]);
        } catch (\Throwable $e) {
            Log::warning('Push: VAPID inválido — '.$e->getMessage());

            return;
        }

        foreach ($inscricoes as $inscricao) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $inscricao->endpoint,
                    'publicKey' => $inscricao->p256dh,
                    'authToken' => $inscricao->auth,
                    // RFC 8291. Sem isto a lib cai no 'aesgcm' legado, que os
                    // serviços de push estão descontinuando.
                    'contentEncoding' => 'aes128gcm',
                ]),
                json_encode($payload, JSON_UNESCAPED_UNICODE),
            );
        }

        foreach ($webPush->flush() as $resultado) {
            if ($resultado->isSuccess()) {
                continue;
            }

            // 404/410 = aparelho desinstalou o app ou revogou a permissão.
            // Guardar a inscrição morta só geraria erro em toda notificação.
            if ($resultado->isSubscriptionExpired()) {
                PushSubscription::where('endpoint_hash', hash('sha256', $resultado->getEndpoint()))->delete();

                continue;
            }

            Log::warning('Push falhou: '.$resultado->getReason());
        }
    }
}
