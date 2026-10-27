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
     * configurado usa o preço base. Serviço "sem preço fixo" (preco = null,
     * ex: produto variável por cliente) retorna null — "a combinar", o valor
     * real é lançado manualmente no agendamento. Ver temPrecoFixo().
     */
    public function precoNoDia(int $diaSemana): ?float
    {
        $porDia = $this->precos_por_dia ?? [];
        $valor = $porDia[$diaSemana] ?? $porDia[(string) $diaSemana] ?? null;

        if ($valor !== null && $valor !== '') {
            return (float) $valor;
        }

        return $this->preco !== null ? (float) $this->preco : null;
    }

    /** Este serviço cobra diferente em algum dia da semana? */
    public function temPrecoVariavel(): bool
    {
        return collect($this->precos_por_dia ?? [])->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
    }

    /** Falso quando o serviço é "sem preço fixo" (preço a combinar, ex: produto variável). */
    public function temPrecoFixo(): bool
    {
        return $this->preco !== null;
    }

    /**
     * Soma o preço de uma coleção de serviços no dia informado. Retorna null
     * ("a combinar") se QUALQUER um dos serviços não tiver preço fixo —
     * não dá pra somar um valor desconhecido, então o total inteiro fica
     * em aberto para o profissional lançar depois.
     */
    public static function somarPrecos(iterable $servicos, int $diaSemana): ?float
    {
        $total = 0.0;
        foreach ($servicos as $servico) {
            $preco = $servico->precoNoDia($diaSemana);
            if ($preco === null) {
                return null;
            }
            $total += $preco;
        }

        return $total;
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
