<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nome', 'telefone', 'tipo', 'limite_cortes_semana', 'valor_mensalidade'])]
class Mensalista extends Model
{
    protected $table = 'mensalistas';

    protected function casts(): array
    {
        return [
            'tipo'                  => 'string',
            'limite_cortes_semana'  => 'integer',
            'valor_mensalidade'     => 'float',
        ];
    }

    public function horariosFixos()
    {
        return $this->hasMany(MensalistaHorarioFixo::class);
    }

    public function agendamentos()
    {
        return $this->hasMany(Agendamento::class);
    }
}
