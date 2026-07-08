<?php

namespace App\Filament\Widgets;

use App\Models\Agendamento;
use App\Models\Profissional;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BarbeiroDashboardWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->isBarbeiro() ?? false;
    }

    protected function getHeading(): ?string
    {
        $nome = explode(' ', auth()->user()->name)[0];
        $hora = (int) now()->format('H');
        $saudacao = match (true) {
            $hora < 12 => 'Bom dia',
            $hora < 18 => 'Boa tarde',
            default    => 'Boa noite',
        };
        return "$saudacao, $nome!";
    }

    protected function getStats(): array
    {
        $pid = auth()->user()->profissional_id;
        if (! $pid) {
            return [Stat::make('Aviso', 'Vincule seu perfil a um profissional')->color('danger')];
        }

        $prof = Profissional::find($pid);
        $percBarbeiro = (float) ($prof?->comissao_percentual ?? 0);

        $hoje = today();
        $inicioMes = now()->startOfMonth();
        $fimMes = now()->endOfMonth();

        // Hoje
        $agsHoje = Agendamento::whereDate('data_hora', $hoje)
            ->where('profissional_id', $pid)
            ->with('servico')->get();

        $pendentesHoje = $agsHoje->where('status', 'pendente')->count();
        $confirmadosHoje = $agsHoje->where('status', 'confirmado')->count();
        $concluidosHoje = $agsHoje->where('status', 'concluido')->count();
        $receitaHoje = $agsHoje->whereIn('status', ['confirmado', 'concluido'])
            ->sum(fn ($a) => $a->servico?->preco ?? 0);

        // Mês
        $agsMes = Agendamento::whereIn('status', ['confirmado', 'concluido'])
            ->whereBetween('data_hora', [$inicioMes, $fimMes])
            ->where('profissional_id', $pid)
            ->with('servico')->get();

        $receitaMes = $agsMes->sum(fn ($a) => $a->servico?->preco ?? 0);
        $totalMes = $agsMes->count();
        $ticketMedio = $totalMes > 0 ? $receitaMes / $totalMes : 0;
        $comissaoMes = round($receitaMes * ($percBarbeiro / 100), 2);

        // Sparkline 7 dias
        $spark = [];
        for ($i = 6; $i >= 0; $i--) {
            $spark[] = Agendamento::whereIn('status', ['confirmado', 'concluido'])
                ->whereDate('data_hora', today()->subDays($i))
                ->where('profissional_id', $pid)
                ->count();
        }

        return [
            Stat::make('Hoje', (string) ($pendentesHoje + $confirmadosHoje + $concluidosHoje))
                ->description("$concluidosHoje ok · $confirmadosHoje conf. · $pendentesHoje pend.")
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('warning'),

            Stat::make('Faturamento Hoje', 'R$ ' . number_format($receitaHoje, 2, ',', '.'))
                ->icon(Heroicon::OutlinedCurrencyDollar)
                ->color('success'),

            Stat::make('Receita do Mês', 'R$ ' . number_format($receitaMes, 2, ',', '.'))
                ->description('ticket médio R$ ' . number_format($ticketMedio, 2, ',', '.'))
                ->chart($spark)
                ->color('success'),

            Stat::make('A Receber (' . $percBarbeiro . '%)', 'R$ ' . number_format($comissaoMes, 2, ',', '.'))
                ->description("$totalMes atendimento(s) no mês")
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('info'),
        ];
    }
}
