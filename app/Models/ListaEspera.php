<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'tenant_id', 'profissional_id', 'servico_id',
    'cliente_nome', 'cliente_telefone', 'data', 'hora_preferida', 'status',
])]
class ListaEspera extends Model
{
    use BelongsToTenant;

    protected $table = 'listas_espera';

    protected function casts(): array
    {
        return [
            'data' => 'date',
        ];
    }

    /** Normaliza a hora para "H:i" (o banco guarda TIME "09:00:00"). */
    protected function horaPreferida(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? substr($value, 0, 5) : $value,
        );
    }

    public function profissional()
    {
        return $this->belongsTo(Profissional::class);
    }

    public function servico()
    {
        return $this->belongsTo(Servico::class);
    }
}
