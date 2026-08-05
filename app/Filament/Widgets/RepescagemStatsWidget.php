<?php

namespace App\Filament\Widgets;

use App\Models\Agendamento;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Cards de destaque no topo da Repescagem: quantos clientes avulsos estão
 * sumidos, em faixas de tempo. Serve para o dono priorizar a repescagem.
 *
 * Usa comparação por data (MAX(data_hora) <= corte) em vez de DATEDIFF/NOW,
 * para funcionar tanto em MySQL (produção) quanto em SQLite (dev/testes).
 */
class RepescagemStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->temPermissao('repescagem') ?? false;
    }

    /** Nº de clientes avulsos distintos cujo último agendamento foi há >= $dias. */
    public function contarAusentes(int $dias): int
    {
        return Agendamento::query()
            ->where('mensalista', false)
            ->whereNotIn('status', ['cancelado'])
            ->groupBy('cliente_telefone')
            ->havingRaw('MAX(data_hora) <= ?', [now()->subDays($dias)->toDateTimeString()])
            ->get(['cliente_telefone'])
            ->count();
    }

    protected function getStats(): array
    {
        $sumidos = $this->contarAusentes(30);
        $antigos = $this->contarAusentes(60);
        $criticos = $this->contarAusentes(90);

        return [
            Stat::make('Clientes sumidos', (string) $sumidos)
                ->description('sem agendar há 30+ dias')
                ->descriptionIcon(Heroicon::OutlinedUserGroup)
                ->color($sumidos > 0 ? 'warning' : 'success'),

            Stat::make('Há 60+ dias', (string) $antigos)
                ->description('vale um empurrãozinho')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color($antigos > 0 ? 'warning' : 'gray'),

            Stat::make('Há 90+ dias', (string) $criticos)
                ->description('risco de perder o cliente')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($criticos > 0 ? 'danger' : 'gray'),
        ];
    }
}
