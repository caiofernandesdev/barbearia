<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'cliente_nome', 'cliente_telefone', 'profissional_id', 'servico_id',
    'data_hora', 'status', 'mensalista', 'observacao',
    'mensalista_id', 'is_avulso_mensalista_fixo',
])]
class Agendamento extends Model
{
    protected $table = 'agendamentos';

    protected function casts(): array
    {
        return [
            'data_hora'                  => 'datetime',
            'mensalista'                 => 'boolean',
            'is_avulso_mensalista_fixo'  => 'boolean',
        ];
    }

    public function profissional()
    {
        return $this->belongsTo(Profissional::class);
    }

    public function servico()
    {
        return $this->belongsTo(Servico::class);
    }

    public function mensalistaCliente()
    {
        return $this->belongsTo(Mensalista::class, 'mensalista_id');
    }
}
