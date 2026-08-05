<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nome', 'foto', 'preco', 'precos_por_dia', 'duracao_minutos', 'ativo', 'destaque', 'ordem', 'tenant_id'])]
class Servico extends Model
{
    use BelongsToTenant;

    protected $table = 'servicos';

    protected function casts(): array
    {
        return [
            'preco' => 'decimal:2',
            'precos_por_dia' => 'array',
            'ativo' => 'boolean',
            'destaque' => 'boolean',
            'ordem' => 'integer',
        ];
    }

    /**
     * Preço deste serviço num dia da semana (0=Dom … 6=Sáb). Dia sem valor
     * configurado usa o preço base.
     */
    public function precoNoDia(int $diaSemana): float
    {
        $porDia = $this->precos_por_dia ?? [];
        $valor = $porDia[$diaSemana] ?? $porDia[(string) $diaSemana] ?? null;

        return $valor !== null && $valor !== ''
            ? (float) $valor
            : (float) $this->preco;
    }

    /** Este serviço cobra diferente em algum dia da semana? */
    public function temPrecoVariavel(): bool
    {
        return collect($this->precos_por_dia ?? [])->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
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
