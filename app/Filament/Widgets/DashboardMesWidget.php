<?php

namespace App\Filament\Widgets;

use App\Models\Agendamento;
use App\Models\Mensalista;
use App\Models\Profissional;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardMesWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    public static function canView(): bool { return auth()->user()?->isAdmin() ?? false; }

    protected function getHeading(): ?string { return 'Resumo do Mês'; }

    protected function getStats(): array
    {
        $tenantId = app()->bound('current_tenant') ? app('current_tenant')?->id : 0;

        $data = Cache::remember("dashboard_mes_data_{$tenantId}", 300, function () {
            $inicioMes    = now()->startOfMonth();
            $fimMes       = now()->endOfMonth();
            $inicioMesAnt = now()->subMonth()->startOfMonth();
            $fimMesAnt    = now()->subMonth()->endOfMonth();

            $agsMes    = Agendamento::whereIn('status', ['confirmado', 'concluido'])->whereBetween('data_hora', [$inicioMes, $fimMes])->with('servico')->get();
            $agsMesAnt = Agendamento::whereIn('status', ['confirmado', 'concluido'])->whereBetween('data_hora', [$inicioMesAnt, $fimMesAnt])->with('servico')->get();

            $receitaMes    = $agsMes->sum(fn ($a) => $a->servico?->preco ?? 0);
            $receitaMesAnt = $agsMesAnt->sum(fn ($a) => $a->servico?->preco ?? 0);
            $varReceita    = $receitaMesAnt > 0 ? round((($receitaMes - $receitaMesAnt) / $receitaMesAnt) * 100, 1) : 0;
            $totalMes      = $agsMes->count();
            $varTotal      = $agsMesAnt->count() > 0 ? round((($totalMes - $agsMesAnt->count()) / $agsMesAnt->count()) * 100, 1) : 0;
            $clientesMes   = $agsMes->pluck('cliente_telefone')->unique()->count();
            $mensalistas   = Mensalista::whereIn('tipo', ['mensalista', 'mensalista_fixo'])->count();

            $cancelados  = Agendamento::where('status', 'cancelado')->whereBetween('data_hora', [$inicioMes, $fimMes])->count();
            $totalCriados = Agendamento::whereBetween('data_hora', [$inicioMes, $fimMes])->count();
            $taxaCancel  = $totalCriados > 0 ? round(($cancelados / $totalCriados) * 100, 1) : 0;

            $comissao = 0;
            foreach ($agsMes->groupBy('profissional_id') as $pid => $profAgs) {
                $prof = Profissional::find($pid);
                $comissao += round($profAgs->sum(fn ($a) => $a->servico?->preco ?? 0) * (($prof?->comissao_percentual ?? 0) / 100), 2);
            }

            $ticketMedio = $totalMes > 0 ? $receitaMes / $totalMes : 0;

            $sparkSemanal = Agendamento::whereIn('status', ['confirmado', 'concluido'])
                ->whereBetween('data_hora', [$inicioMes, now()])
                ->select([DB::raw('WEEK(data_hora) as semana'), DB::raw('COALESCE(SUM(servicos.preco),0) as receita')])
                ->leftJoin('servicos', 'servicos.id', '=', 'agendamentos.servico_id')
                ->groupBy('semana')->orderBy('semana')->pluck('receita')->map(fn ($v) => (int) $v)->toArray();

            return compact('receitaMes', 'varReceita', 'totalMes', 'varTotal', 'clientesMes', 'mensalistas',
                'cancelados', 'taxaCancel', 'comissao', 'ticketMedio', 'sparkSemanal');
        });

        return [
            Stat::make('Receita do Mês', 'R$ ' . number_format($data['receitaMes'], 2, ',', '.'))
                ->description($data['varReceita'] >= 0 ? "+{$data['varReceita']}% vs mês anterior" : "{$data['varReceita']}% vs mês anterior")
                ->descriptionIcon($data['varReceita'] >= 0 ? Heroicon::OutlinedArrowTrendingUp : Heroicon::OutlinedArrowTrendingDown)
                ->chart($data['sparkSemanal'] ?: [0])
                ->color($data['varReceita'] >= 0 ? 'success' : 'danger'),

            Stat::make('Atendimentos', (string) $data['totalMes'])
                ->description('ticket médio R$ ' . number_format($data['ticketMedio'], 2, ',', '.'))
                ->descriptionIcon($data['varTotal'] >= 0 ? Heroicon::OutlinedArrowTrendingUp : Heroicon::OutlinedArrowTrendingDown)
                ->color($data['varTotal'] >= 0 ? 'success' : 'danger'),

            Stat::make('Clientes Únicos', (string) $data['clientesMes'])
                ->description("{$data['mensalistas']} mensalista(s)")
                ->icon(Heroicon::OutlinedUserGroup)
                ->color('info'),

            Stat::make('Comissões', 'R$ ' . number_format($data['comissao'], 2, ',', '.'))
                ->description("cancelamentos: {$data['cancelados']} ({$data['taxaCancel']}%)")
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('warning'),
        ];
    }
}
