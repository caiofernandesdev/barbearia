<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'endpoint', 'endpoint_hash', 'p256dh', 'auth'])]
class PushSubscription extends Model
{
    protected $table = 'push_subscriptions';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Grava (ou atualiza) a inscrição do aparelho. O endpoint é longo demais
     * para índice único, então a unicidade sai do hash dele.
     */
    public static function registrar(User $user, string $endpoint, string $p256dh, string $auth): self
    {
        return static::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $endpoint)],
            [
                'user_id' => $user->id,
                'endpoint' => $endpoint,
                'p256dh' => $p256dh,
                'auth' => $auth,
            ],
        );
    }
}
