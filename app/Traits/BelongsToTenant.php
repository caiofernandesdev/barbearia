<?php

namespace App\Traits;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        // Aplica o filtro automático por tenant em todas as queries
        static::addGlobalScope(new TenantScope());

        // Injeta tenant_id automaticamente ao criar registros
        static::creating(function ($model) {
            if (empty($model->tenant_id) && app()->bound('current_tenant')) {
                $tenant = app('current_tenant');
                if ($tenant) {
                    $model->tenant_id = $tenant->id;
                }
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
}
