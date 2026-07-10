<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'nome_barbearia',
    'logo',
    'dias_funcionamento',
    'mensalista_limite_cortes_semana',
    'horario_abertura',
    'horario_encerramento',
    'intervalo_minutos',
    'percentual_barbearia',
    'dias_antecedencia_lembrete',
    'mensagem_repescagem',
    'cancelar_nao_confirmados',
    'horas_antecedencia_cancelamento',
    'tema_agendamento',
    'tenant_id',
])]
class ConfiguracaoBarbearia extends Model
{
    use BelongsToTenant;

    protected $table = 'configuracoes_barbearia';

    // Singleton por tenant — chaveado por tenant_id para suportar multi-tenancy
    private static array $instances = [];

    protected static function booted(): void
    {
        static::saved(fn () => static::$instances = []);
        static::deleted(fn () => static::$instances = []);
    }

    protected function casts(): array
    {
        return [
            'dias_funcionamento' => 'array',
            'mensalista_limite_cortes_semana' => 'integer',
            'cancelar_nao_confirmados' => 'boolean',
            'horas_antecedencia_cancelamento' => 'integer',
            'intervalo_minutos' => 'integer',
            'percentual_barbearia' => 'decimal:2',
            'dias_antecedencia_lembrete' => 'integer',
        ];
    }

    public static function getInstance(): self
    {
        $tenantId = app()->bound('current_tenant') ? (app('current_tenant')?->id ?? 0) : 0;

        if (! isset(static::$instances[$tenantId]) || ! (static::$instances[$tenantId] instanceof self)) {
            $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;
            $nomePadrao = $tenant?->nomePadrao() ?? 'Estabelecimento';

            static::$instances[$tenantId] = static::firstOrCreate([], [
                'nome_barbearia' => $nomePadrao,
                'mensalista_limite_cortes_semana' => 1,
                'horario_abertura' => '08:00',
                'horario_encerramento' => '19:00',
                'intervalo_minutos' => 60,
            ]);
        }

        return static::$instances[$tenantId];
    }
}
