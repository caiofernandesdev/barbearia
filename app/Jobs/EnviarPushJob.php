<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\PushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Push é chamada de rede — sai da requisição do agendamento e vai pra fila,
 * igual ao WhatsApp. Falhar aqui nunca pode derrubar um agendamento.
 */
class EnviarPushJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** @param  array<string, mixed>  $payload */
    public function __construct(
        public int $userId,
        public array $payload,
    ) {}

    public function handle(PushService $push): void
    {
        $user = User::withoutGlobalScopes()->find($this->userId);

        if (! $user) {
            return;
        }

        $push->enviarPara($user, $this->payload);
    }
}
