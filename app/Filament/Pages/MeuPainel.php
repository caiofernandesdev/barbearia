<?php

namespace App\Filament\Pages;

use App\Models\Agendamento;
use App\Models\ConfiguracaoBarbearia;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MeuPainel extends Page
{

    protected string $view = 'filament.pages.meu-painel';

    protected static ?string $navigationLabel = 'Meu Painel';

    protected static ?string $title = 'Meu Painel';

    protected static \BackedEnum|string|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?int $navigationSort = 1;

    public string $dataInicio   = '';
    public string $dataFim      = '';
    public string $periodoAtivo = 'hoje';

    public static function canAccess(): bool
    {
        return auth()->user()?->isBarbeiro() ?? false;
    }

    public function mount(): void
    {
        $this->dataInicio = now()->format('Y-m-d');
        $this->dataFim    = now()->format('Y-m-d');
    }

    // ─── Atalhos de período ───────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Action::make('hoje')
                ->label('Hoje')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color(fn () => $this->periodoAtivo === 'hoje' ? 'warning' : 'gray')
                ->action(function () {
                    $this->dataInicio   = now()->format('Y-m-d');
                    $this->dataFim      = now()->format('Y-m-d');
                    $this->periodoAtivo = 'hoje';
                }),

            Action::make('semana')
                ->label('Esta Semana')
                ->icon(Heroicon::OutlinedCalendar)
                ->color(fn () => $this->periodoAtivo === 'semana' ? 'warning' : 'gray')
                ->action(function () {
                    $this->dataInicio   = now()->startOfWeek()->format('Y-m-d');
                    $this->dataFim      = now()->endOfWeek()->format('Y-m-d');
                    $this->periodoAtivo = 'semana';
                }),

            Action::make('mes')
                ->label('Este Mês')
                ->icon(Heroicon::OutlinedCalendar)
                ->color(fn () => $this->periodoAtivo === 'mes' ? 'warning' : 'gray')
                ->action(function () {
                    $this->dataInicio   = now()->startOfMonth()->format('Y-m-d');
                    $this->dataFim      = now()->endOfMonth()->format('Y-m-d');
                    $this->periodoAtivo = 'mes';
                }),
        ];
    }

    // ─── Filtros ──────────────────────────────────────────────────────────────

    public function updatedDataInicio(): void { $this->periodoAtivo = ''; }
    public function updatedDataFim(): void    { $this->periodoAtivo = ''; }

    public function filterForm(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('dataInicio')
                ->label('De')
                ->live()
                ->native(false)
                ->displayFormat('d/m/Y'),

            DatePicker::make('dataFim')
                ->label('Até')
                ->live()
                ->native(false)
                ->displayFormat('d/m/Y'),
        ])->columns(2);
    }

    // ─── Stat cards ───────────────────────────────────────────────────────────

    public function statsSchema(Schema $schema): Schema
    {
        $r = $this->calcResumo();

        return $schema->components([
            Stat::make('Atendimentos', (string) $r['total'])
                ->description('no período selecionado')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('warning'),

            Stat::make('Receita Gerada', 'R$ ' . number_format($r['receita'], 2, ',', '.'))
                ->description('ticket médio R$ ' . number_format($r['ticket'], 2, ',', '.'))
                ->icon(Heroicon::OutlinedCurrencyDollar)
                ->color('success'),

            Stat::make('A Receber (' . $r['percBarbeiro'] . '%)', 'R$ ' . number_format($r['comissao'], 2, ',', '.'))
                ->description('comissão sobre serviços realizados')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('info'),
        ])->columns(3);
    }

    // ─── Cálculo ──────────────────────────────────────────────────────────────

    private function calcResumo(): array
    {
        $inicio = $this->dataInicio ?: now()->startOfMonth()->format('Y-m-d');
        $fim    = $this->dataFim    ?: now()->endOfMonth()->format('Y-m-d');
        $pid    = auth()->user()->profissional_id;

        $config       = ConfiguracaoBarbearia::getInstance();
        $percBarbeiro = round(100 - (float) ($config->percentual_barbearia ?? 60), 2);

        $ags = Agendamento::whereIn('status', ['confirmado', 'concluido'])
            ->whereBetween('data_hora', [$inicio . ' 00:00:00', $fim . ' 23:59:59'])
            ->when($pid, fn ($q) => $q->where('profissional_id', $pid))
            ->with('servico')
            ->get();

        $receita  = $ags->sum(fn ($a) => $a->servico?->preco ?? 0);
        $total    = $ags->count();
        $ticket   = $total > 0 ? $receita / $total : 0;
        $comissao = $receita * ($percBarbeiro / 100);

        return compact('total', 'receita', 'ticket', 'comissao', 'percBarbeiro');
    }
}
