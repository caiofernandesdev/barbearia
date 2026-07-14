<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nome', 'telefone', 'foto', 'limite_mensalistas', 'comissao_percentual', 'ativo', 'horarios_trabalho', 'horarios_por_dia', 'horarios_por_dia_ativo', 'dias_trabalho', 'tenant_id'])]
class Profissional extends Model
{
    use BelongsToTenant;

    protected $table = 'profissionais';

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'limite_mensalistas' => 'integer',
            'comissao_percentual' => 'float',
            'horarios_trabalho' => 'array',
            'horarios_por_dia' => 'array', // ex: {"1": ["08:00"], "6": ["10:00"]}
            'horarios_por_dia_ativo' => 'boolean',
            'dias_trabalho' => 'array', // ex: [1,2,3,4,5,6] = Seg a Sáb
        ];
    }

    /**
     * Horários que este profissional atende num dia da semana (0=Dom ... 6=Sáb).
     *
     * Cascata: horários daquele dia (se ele configurou por dia) → lista única de
     * horarios_trabalho → vazio, que faz o DisponibilidadeService cair no modo
     * gap-based (gera slots pelo expediente do estabelecimento).
     *
     * @return array<int, string>
     */
    public function horariosDoDia(int $diaSemana): array
    {
        if ($this->horarios_por_dia_ativo) {
            $porDia = $this->horarios_por_dia ?? [];

            // Chaves de JSON voltam como string; aceita os dois formatos
            return array_values($porDia[$diaSemana] ?? $porDia[(string) $diaSemana] ?? []);
        }

        return $this->horarios_trabalho ?? [];
    }

    public function agendamentos()
    {
        return $this->hasMany(Agendamento::class);
    }

    /** Serviços que este profissional realiza. Vazio = atende todos os serviços. */
    public function servicos()
    {
        return $this->belongsToMany(Servico::class, 'profissional_servico');
    }
}
