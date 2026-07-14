<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nome', 'descricao', 'preco_mensal', 'features', 'max_profissionais', 'max_usuarios', 'ativo'])]
class Plano extends Model
{
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
}
