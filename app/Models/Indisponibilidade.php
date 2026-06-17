<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['profissional_id', 'inicio', 'fim', 'motivo'])]
class Indisponibilidade extends Model
{
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
