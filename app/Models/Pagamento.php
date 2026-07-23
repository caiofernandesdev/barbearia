<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pagamento de mensalidade de um tenant ao Atendix. Registro do histórico
 * financeiro do SaaS — separado do financeiro do estabelecimento.
 */
#[Fillable(['tenant_id', 'valor', 'competencia', 'pago_em', 'forma', 'observacao', 'comprovante'])]
class Pagamento extends Model
{
    protected $table = 'pagamentos';

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'pago_em' => 'date',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
