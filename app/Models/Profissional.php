<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nome', 'foto', 'limite_mensalistas', 'comissao_percentual', 'ativo', 'horarios_trabalho'])]
class Profissional extends Model
{
    protected $table = 'profissionais';

    protected function casts(): array
    {
        return [
            'ativo'                => 'boolean',
            'limite_mensalistas'   => 'integer',
            'comissao_percentual'  => 'float',
            'horarios_trabalho'    => 'array', // ex: ["08:00", "09:00", ...]
        ];
    }

    public function agendamentos()
    {
        return $this->hasMany(Agendamento::class);
    }
}
