<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['nome', 'slug', 'descricao', 'preco_mensal', 'features', 'max_profissionais', 'max_usuarios', 'ativo'])]
class Plano extends Model
{
    /** Plano novo nasce com slug derivado do nome, se ninguém informar */
    protected static function booted(): void
    {
        static::creating(function (self $plano) {
            $plano->slug ??= Str::slug($plano->nome ?? '');
        });
    }

    /**
     * Relatórios granulares (sub-módulos do módulo 'relatorios').
     * Slug (vai no array features) => rótulo exibido no super admin.
     */
    public const RELATORIOS = [
        'rel_atendimentos' => 'Atendimentos',
        'rel_receita' => 'Receita Total (+ ticket médio)',
        'rel_clientes_unicos' => 'Clientes Únicos',
        'rel_cancelamentos' => 'Cancelamentos',
        'rel_servico_top' => 'Serviço + Realizado',
        'rel_desempenho_barbeiro' => 'Desempenho por Profissional (+ comissões)',
        'rel_evolucao_mensal' => 'Evolução Mensal (últimos 6 meses)',
        'rel_agendamentos_periodo' => 'Agendamentos do Período',
    ];

    protected $table = 'planos';

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'preco_mensal' => 'decimal:2',
            'max_profissionais' => 'integer',
            'max_usuarios' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    public function tenants()
    {
        return $this->hasMany(Tenant::class);
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    /** Limites do plano em texto: "5 profissionais · 5 logins" (0 = ilimitado) */
    public function getLimitesResumoAttribute(): string
    {
        $prof = $this->max_profissionais > 0
            ? $this->max_profissionais.($this->max_profissionais === 1 ? ' profissional' : ' profissionais')
            : 'Profissionais ilimitados';

        $logins = $this->max_usuarios > 0
            ? $this->max_usuarios.($this->max_usuarios === 1 ? ' login' : ' logins')
            : 'logins ilimitados';

        return $prof.' · '.$logins;
    }

    /**
     * Preço em pt-BR para exibição (landing). Omite os centavos quando
     * são zero: 97.00 => "97" e 79.90 => "79,90".
     */
    public function getPrecoFormatadoAttribute(): string
    {
        $valor = (float) $this->preco_mensal;

        return fmod($valor, 1.0) === 0.0
            ? number_format($valor, 0, ',', '.')
            : number_format($valor, 2, ',', '.');
    }
}
