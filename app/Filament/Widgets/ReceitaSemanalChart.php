<?php

namespace App\Filament\Widgets;

use App\Models\Agendamento;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReceitaSemanalChart extends ChartWidget
{
    protected static ?int $sort = 3;

    public static function canView(): bool { return auth()->user()?->isAdmin() ?? false; }

    protected ?string $maxHeight = '250px';

    public function getHeading(): ?string { return 'Faturamento — Últimos 14 dias'; }

    protected function getData(): array
    {
        $tenantId = app()->bound('current_tenant') ? app('current_tenant')?->id : 0;

        return Cache::remember("chart_receita_data_{$tenantId}", 300, function () {
            $dados = Agendamento::whereIn('status', ['confirmado', 'concluido'])
                ->where('data_hora', '>=', today()->subDays(13)->startOfDay())
                ->select([DB::raw('DATE(data_hora) as dia'), DB::raw('COUNT(*) as total'), DB::raw('COALESCE(SUM(servicos.preco), 0) as receita')])
                ->leftJoin('servicos', 'servicos.id', '=', 'agendamentos.servico_id')
                ->groupBy('dia')->orderBy('dia')
                ->get()->keyBy('dia');

            $labels = []; $receita = []; $ags = [];
            for ($i = 13; $i >= 0; $i--) {
                $d = today()->subDays($i)->format('Y-m-d');
                $labels[]  = today()->subDays($i)->format('d/m');
                $row       = $dados[$d] ?? null;
                $receita[] = $row ? round((float) $row->receita, 2) : 0;
                $ags[]     = $row ? (int) $row->total : 0;
            }

            return [
                'datasets' => [
                    ['label' => 'Receita (R$)', 'data' => $receita, 'borderColor' => '#10b981', 'backgroundColor' => 'rgba(16,185,129,0.1)', 'fill' => true, 'tension' => 0.3],
                    ['label' => 'Agendamentos', 'data' => $ags, 'borderColor' => '#f59e0b', 'backgroundColor' => 'rgba(245,158,11,0.1)', 'fill' => true, 'tension' => 0.3],
                ],
                'labels' => $labels,
            ];
        });
    }

    protected function getType(): string { return 'line'; }
}
