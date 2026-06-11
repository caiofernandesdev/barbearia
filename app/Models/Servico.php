<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nome', 'foto', 'preco', 'duracao_minutos', 'ativo', 'destaque', 'ordem'])]
class Servico extends Model
{
    protected $table = 'servicos';

    protected function casts(): array
    {
        return [
            'preco' => 'decimal:2',
            'ativo' => 'boolean',
            'destaque' => 'boolean',
        ];
    }

    public function agendamentos()
    {
        return $this->hasMany(Agendamento::class);
    }
}
