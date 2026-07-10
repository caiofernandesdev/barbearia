<?php

namespace App\Filament\Widgets;

use App\Models\Agendamento;
use App\Models\ConfiguracaoBarbearia;
use App\Models\Profissional;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardHeaderWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function getHeading(): ?string
    {
        $nome = explode(' ', auth()->user()->name)[0];
        $hora = (int) now()->format('H');
        $saudacao = match (true) {
            $hora < 12 => 'Bom dia', $hora < 18 => 'Boa tarde', default => 'Boa noite'
        };

        return "$saudacao, $nome!";
    }

    protected function getDescription(): ?string
    {
        $config = ConfiguracaoBarbearia::getInstance();

        return $config->nome_barbearia.' — '.now()->locale('pt_BR')->isoFormat('dddd, D [de] MMMM [de] YYYY');
    }

    protected function getStats(): array
    {
        $tenantId = app()->bound('current_tenant') ? app('current_tenant')?->id : 0;

        $data = Cache::remember("dashboard_header_data_{$tenantId}", 120, function () {
            $hoje = today();
            $agsHoje = Agendamento::whereDate('data_hora', $hoje)->with('servico')->get();

            $receitaHoje = $agsHoje->whereIn('status', ['confirmado', 'concluido'])->sum(fn ($a) => $a->valor_total ?? $a->servico?->preco ?? 0);
            $pendentes = $agsHoje->where('status', 'pendente')->count();
            $confirmados = $agsHoje->where('status', 'confirmado')->count();
            $concluidos = $agsHoje->where('status', 'concluido')->count();
            $totalHoje = $pendentes + $confirmados + $concluidos;
            $clientesHoje = $agsHoje->whereIn('status', ['confirmado', 'concluido'])->pluck('cliente_telefone')->unique()->count();

            $semana = Agendamento::whereIn('status', ['confirmado', 'concluido'])
                ->where('data_hora', '>=', today()->subDays(6)->startOfDay())
                ->select([DB::raw('DATE(data_hora) as dia'), DB::raw('COUNT(*) as total')])
                ->groupBy('dia')->pluck('total', 'dia');

            $sparkAgs = [];
            for ($i = 6; $i >= 0; $i--) {
                $sparkAgs[] = $semana[today()->subDays($i)->format('Y-m-d')] ?? 0;
            }

            $config = ConfiguracaoBarbearia::getInstance();
            $intervalo = $config->intervalo_minutos ?? 60;
            $totalBarbeiros = Profissional::where('ativo', true)->count();
            $totalSlots = 0;
            if ($intervalo > 0 && $totalBarbeiros > 0) {
                $min = (strtotime($config->horario_encerramento ?? '19:00') - strtotime($config->horario_abertura ?? '08:00')) / 60;
                $totalSlots = max(1, floor($min / $intervalo)) * $totalBarbeiros;
            }
            $ocupacao = $totalSlots > 0 ? round(($totalHoje / $totalSlots) * 100, 1) : 0;

            return compact('receitaHoje', 'pendentes', 'confirmados', 'concluidos', 'totalHoje', 'clientesHoje', 'sparkAgs', 'totalBarbeiros', 'totalSlots', 'ocupacao');
        });

        return [
            Stat::make('Faturamento Hoje', 'R$ '.number_format($data['receitaHoje'], 2, ',', '.'))
                ->description('últimos 7 dias')
                ->descriptionIcon(Heroicon::OutlinedArrowTrendingUp)
                ->chart($data['sparkAgs'])
                ->color('success'),

            Stat::make('Agendamentos Hoje', (string) $data['totalHoje'])
                ->description("{$data['concluidos']} ok · {$data['confirmados']} conf. · {$data['pendentes']} pend.")
                ->chart($data['sparkAgs'])
                ->color('warning'),

            Stat::make('Clientes Hoje', (string) $data['clientesHoje'])
                ->description("{$data['totalBarbeiros']} barbeiro(s)")
                ->icon(Heroicon::OutlinedUsers)
                ->color('info'),

            Stat::make('Ocupação', $data['ocupacao'].'%')
                ->description("{$data['totalHoje']} de {$data['totalSlots']} slots")
                ->icon(Heroicon::OutlinedChartBar)
                ->color($data['ocupacao'] > 70 ? 'success' : ($data['ocupacao'] > 40 ? 'warning' : 'danger')),
        ];
    }
}
