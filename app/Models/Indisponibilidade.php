<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['profissional_id', 'inicio', 'fim', 'motivo', 'tenant_id'])]
class Indisponibilidade extends Model
{
    use BelongsToTenant;
    protected $table = 'indisponibilidades';

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fim'    => 'datetime',
        ];
    }

    public function profissional()
    {
        return $this->belongsTo(Profissional::class);
    }

    // Retorna label amigável do escopo
    public function getEscopoAttribute(): string
    {
        return $this->profissional_id
            ? ($this->profissional->nome ?? 'Profissional')
            : 'Toda a barbearia';
    }
}
