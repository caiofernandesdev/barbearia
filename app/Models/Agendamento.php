<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'cliente_nome', 'cliente_telefone', 'profissional_id', 'servico_id',
    'data_hora', 'status', 'mensalista', 'observacao',
    'mensalista_id', 'is_avulso_mensalista_fixo', 'dados_extras', 'tenant_id',
])]
class Agendamento extends Model
{
    use BelongsToTenant;

    protected $table = 'agendamentos';

    protected function casts(): array
    {
        return [
            'data_hora'                  => 'datetime',
            'mensalista'                 => 'boolean',
            'is_avulso_mensalista_fixo'  => 'boolean',
            'dados_extras'               => 'array',
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
