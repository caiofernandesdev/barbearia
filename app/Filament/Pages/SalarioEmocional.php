<?php

namespace App\Filament\Pages;

use App\Models\Agendamento;
use App\Models\Mensalista;
use App\Models\Profissional;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalarioEmocional extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.salario-emocional';

    protected static ?string $navigationLabel = 'Salário Emocional';

    protected static ?string $title = 'Salário Emocional';

    protected static \BackedEnum|string|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 10;

    public array $data = [];

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function mount(): void
    {
        $profId = auth()->user()?->isBarbeiro()
            ? auth()->user()->profissional_id
            : null;

        $this->data = [
            'data_inicio'     => now()->startOfMonth()->format('Y-m-d'),
            'data_fim'        => now()->format('Y-m-d'),
            'profissional_id' => $profId,
        ];
    }

    // ─── Filtros ──────────────────────────────────────────────────────────────

    public function filterForm(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('data_inicio')
                ->label('Data inicial')
                ->required()
                ->live(),

            DatePicker::make('data_fim')
                ->label('Data final')
                ->required()
                ->live(),

            Select::make('profissional_id')
                ->label('Barbeiro')
                ->options(Profissional::where('ativo', true)->pluck('nome', 'id'))
                ->placeholder('Todos os barbeiros')
                ->live()
                ->hidden(fn () => auth()->user()?->isBarbeiro()),
        ])->columns(3)->statePath('data');
    }

    // ─── Resumo (stat cards nativos) ─────────────────────────────────────────

    public function resumoSchema(Schema $schema): Schema
    {
        $r = $this->calcResumo();

        return $schema->components([
            Stat::make('Mensalistas ativos', (string) $r['qtdMensalistas'])
                ->description('planos vigentes')
                ->icon(Heroicon::OutlinedUsers)
                ->color('info'),

            Stat::make('Receita total', 'R$ ' . number_format($r['receitaTotal'], 2, ',', '.'))
                ->description('soma das mensalidades individuais')
                ->icon(Heroicon::OutlinedCurrencyDollar)
                ->color('success'),

            Stat::make('Para a barbearia (50%)', 'R$ ' . number_format($r['valorBarbearia'], 2, ',', '.'))
                ->icon(Heroicon::OutlinedReceiptPercent)
                ->color('warning'),

            Stat::make('Fundo Salário Emocional', 'R$ ' . number_format($r['fundoSE'], 2, ',', '.'))
                ->description('50% — distribuído proporcionalmente')
                ->icon(Heroicon::OutlinedStar)
                ->color('success'),

            Stat::make('Atend. mensalistas', (string) $r['totalAtendimentos'])
                ->description('concluídos no período')
                ->icon(Heroicon::OutlinedScissors)
                ->color('warning'),
        ])->columns(5);
    }

    // ─── Tabela nativa Filament ───────────────────────────────────────────────

    public function table(Table $table): Table
    {
        [$inicio, $fim]  = $this->datas();
        $profissionalId  = $this->data['profissional_id'] ?? null;
        $resumo          = $this->calcResumo();
        $statsMap        = $this->buildStatsMap($resumo['totalAtendimentos'], $resumo['fundoSE'], $inicio, $fim);

        return $table
            ->query(
                Profissional::query()
                    ->where('ativo', true)
                    ->when($profissionalId, fn($q) => $q->where('id', $profissionalId))
                    ->orderBy('nome')
            )
            ->heading('Detalhamento por Barbeiro')
            ->description('Participação proporcional + consolidação financeira do período')
            ->columns([
                TextColumn::make('nome')
                    ->label('Barbeiro')
                    ->weight(FontWeight::SemiBold),

                TextColumn::make('qtd_mensalistas')
                    ->label('Atend. Mensalistas')
                    ->getStateUsing(fn($record) => $statsMap[$record->id]['qtd_mensalistas'] ?? 0)
                    ->alignCenter(),

                TextColumn::make('pct')
                    ->label('Participação')
                    ->getStateUsing(fn($record) => ($statsMap[$record->id]['pct'] ?? 0) . '%')
                    ->alignCenter()
                    ->badge()
                    ->color('info'),

                TextColumn::make('valor_se')
                    ->label('Sal. Emocional')
                    ->getStateUsing(fn($record) => 'R$ ' . number_format($statsMap[$record->id]['valor_se'] ?? 0, 2, ',', '.'))
                    ->color('success')
                    ->weight(FontWeight::SemiBold)
                    ->alignEnd(),

                TextColumn::make('total_servicos')
                    ->label('Serviços')
                    ->getStateUsing(fn($record) => $statsMap[$record->id]['total_servicos'] ?? 0)
                    ->alignCenter(),

                TextColumn::make('receita_servicos')
                    ->label('Receita')
                    ->getStateUsing(fn($record) => 'R$ ' . number_format($statsMap[$record->id]['receita_servicos'] ?? 0, 2, ',', '.'))
                    ->alignEnd(),

                TextColumn::make('comissao_pct')
                    ->label('% Com.')
                    ->getStateUsing(fn($record) => ($record->comissao_percentual ?? 0) . '%')
                    ->alignCenter(),

                TextColumn::make('comissao_valor')
                    ->label('Comissão')
                    ->getStateUsing(fn($record) => 'R$ ' . number_format($statsMap[$record->id]['comissao_valor'] ?? 0, 2, ',', '.'))
                    ->color('warning')
                    ->alignEnd(),

                TextColumn::make('total_receber')
                    ->label('Total a Receber')
                    ->getStateUsing(fn($record) => 'R$ ' . number_format($statsMap[$record->id]['total_receber'] ?? 0, 2, ',', '.'))
                    ->weight(FontWeight::Bold)
                    ->color('success')
                    ->alignEnd(),
            ])
            ->striped()
            ->paginated(false);
    }

    // ─── Cálculos ─────────────────────────────────────────────────────────────

    private function datas(): array
    {
        $inicio = Carbon::parse($this->data['data_inicio'] ?? now()->startOfMonth())->startOfDay();
        $fim    = Carbon::parse($this->data['data_fim'] ?? now())->endOfDay();
        return [$inicio, $fim];
    }

    private function calcResumo(): array
    {
        [$inicio, $fim] = $this->datas();

        $qtdMensalistas = Mensalista::whereIn('tipo', ['mensalista', 'mensalista_fixo'])->count();
        $receitaTotal   = round((float) Mensalista::whereIn('tipo', ['mensalista', 'mensalista_fixo'])->sum('valor_mensalidade'), 2);
        $fundoSE        = round($receitaTotal * 0.5, 2);
        $valorBarbearia = round($receitaTotal * 0.5, 2);

        $totalAtendimentos = Agendamento::where('status', 'concluido')
            ->whereNotNull('mensalista_id')
            ->whereBetween('data_hora', [$inicio, $fim])
            ->count();

        return compact('qtdMensalistas', 'receitaTotal', 'fundoSE', 'valorBarbearia', 'totalAtendimentos');
    }

    private function buildStatsMap(int $totalAtendimentos, float $fundoSE, Carbon $inicio, Carbon $fim): array
    {
        $map = [];

        foreach (Profissional::where('ativo', true)->get() as $p) {
            $qtdMensalistas = Agendamento::where('status', 'concluido')
                ->whereNotNull('mensalista_id')
                ->where('profissional_id', $p->id)
                ->whereBetween('data_hora', [$inicio, $fim])
                ->count();

            $pct     = $totalAtendimentos > 0 ? round($qtdMensalistas / $totalAtendimentos * 100, 1) : 0;
            $valorSE = $totalAtendimentos > 0 ? round($qtdMensalistas / $totalAtendimentos * $fundoSE, 2) : 0;

            $todosAgs = Agendamento::where('status', 'concluido')
                ->where('profissional_id', $p->id)
                ->whereBetween('data_hora', [$inicio, $fim])
                ->with('servico')
                ->get();

            $totalServicos   = $todosAgs->count();
            $receitaServicos = round($todosAgs->sum(fn($a) => $a->servico?->preco ?? 0), 2);
            $comissaoPct     = (float) ($p->comissao_percentual ?? 0);
            $comissaoValor   = round($receitaServicos * ($comissaoPct / 100), 2);
            $totalReceber    = round($comissaoValor + $valorSE, 2);

            $map[$p->id] = [
                'qtd_mensalistas'  => $qtdMensalistas,
                'pct'              => $pct,
                'valor_se'         => $valorSE,
                'total_servicos'   => $totalServicos,
                'receita_servicos' => $receitaServicos,
                'comissao_pct'     => $comissaoPct,
                'comissao_valor'   => $comissaoValor,
                'total_receber'    => $totalReceber,
            ];
        }

        return $map;
    }
}
