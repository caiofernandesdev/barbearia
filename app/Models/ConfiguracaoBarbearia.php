<?php

namespace App\Models;

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
])]
class ConfiguracaoBarbearia extends Model
{
    protected $table = 'configuracoes_barbearia';

    // Singleton em memória — uma única query por request, sem serialização de cache
    private static ?self $instance = null;

    protected static function booted(): void
    {
        // Descarta o singleton quando o admin salva, para que a próxima request leia o novo valor
        static::saved(fn () => static::$instance = null);
        static::deleted(fn () => static::$instance = null);
    }

    protected function casts(): array
    {
        return [
            'dias_funcionamento'               => 'array',
            'mensalista_limite_cortes_semana'  => 'integer',
            'intervalo_minutos'                => 'integer',
            'percentual_barbearia'             => 'decimal:2',
        ];
    }

    public static function getInstance(): self
    {
        if (static::$instance === null) {
            static::$instance = static::firstOrCreate([], [
                'nome_barbearia'                  => 'Barbearia',
                'dias_funcionamento'              => [1, 2, 3, 4, 5, 6],
                'mensalista_limite_cortes_semana' => 1,
                'horario_abertura'               => '08:00',
                'horario_encerramento'           => '19:00',
                'intervalo_minutos'              => 60,
            ]);
        }

        return static::$instance;
    }
}