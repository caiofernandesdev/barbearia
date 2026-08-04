<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nome', 'foto', 'preco', 'duracao_minutos', 'ativo', 'destaque', 'ordem', 'tenant_id'])]
class Servico extends Model
{
    use BelongsToTenant;

    protected $table = 'servicos';

    protected function casts(): array
    {
        return [
            'preco' => 'decimal:2',
            'ativo' => 'boolean',
            'destaque' => 'boolean',
            'ordem' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Serviço novo sem ordem vai para o FIM (max + 1), não para o começo.
        // Antes ficava em 0 e, como 0 < 1, aparecia antes dos ordenados à mão.
        static::creating(function (Servico $servico) {
            if (empty($servico->ordem)) {
                $servico->ordem = ((int) static::max('ordem')) + 1;
            }
        });
    }

    public function agendamentos()
    {
        return $this->hasMany(Agendamento::class);
    }
}
