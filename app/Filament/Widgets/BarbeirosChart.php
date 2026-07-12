<?php

namespace App\Filament\Widgets;

use App\Models\Agendamento;
use App\Models\Profissional;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class BarbeirosChart extends ChartWidget
{
    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected ?string $maxHeight = '250px';

    public function getHeading(): ?string
    {
        return 'Atendimentos por Profissional — Este Mês';
    }

    protected function getData(): array
    {
        $tenantId = app()->bound('current_tenant') ? app('current_tenant')?->id : 0;

        return Cache::remember("chart_barbeiros_data_{$tenantId}", 300, function () {
            $profissionais = Profissional::where('ativo', true)->orderBy('nome')->get();

            $counts = Agendamento::whereIn('status', ['confirmado', 'concluido'])
                ->whereBetween('data_hora', [now()->startOfMonth(), now()->endOfMonth()])
                ->selectRaw('profissional_id, COUNT(*) as total')
                ->groupBy('profissional_id')
                ->pluck('total', 'profissional_id');

            $labels = [];
            $dados = [];
            $cores = ['#f59e0b', '#10b981', '#3b82f6', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6'];

            foreach ($profissionais as $prof) {
                $labels[] = $prof->nome;
                $dados[] = $counts[$prof->id] ?? 0;
            }

            return [
                'datasets' => [['label' => 'Atendimentos', 'data' => $dados, 'backgroundColor' => array_slice($cores, 0, count($profissionais)), 'borderWidth' => 0]],
                'labels' => $labels,
            ];
        });
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
