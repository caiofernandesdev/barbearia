<?php

namespace App\Filament\Pages;

use App\Models\Agendamento;
use App\Models\Profissional;
use Carbon\Carbon;
use Filament\Actions\Action;
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
use Illuminate\Support\Facades\DB;

class Relatorios extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.relatorios';

    protected static ?string $navigationLabel = 'Relatórios';

    protected static ?string $title = 'Relatórios';

    protected static \BackedEnum|string|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): string
    {
        return 'Financeiro';
    }

    public static function canAccess(): bool
    {
        if (! auth()->user()?->isAdmin()) {
            return false;
        }
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;

        return $tenant?->hasFeature('relatorios') ?? false;
    }

    // Propriedades Livewire individuais para reatividade confiável
    public string $dataInicio = '';

    public string $dataFim = '';

    public ?string $filtroProfissional = null;

    public ?string $filtroStatus = null;

    public function mount(): void
    {
        $this->dataInicio = now()->startOfMonth()->format('Y-m-d');
        $this->dataFim = now()->endOfMonth()->format('Y-m-d');
    }

    /** O plano do tenant inclui este relatório? (usado também pelo blade da página) */
    public function temRelatorio(string $slug): bool
    {
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;

        return $tenant?->hasRelatorio($slug) ?? false;
    }

    // ─── Ações do cabeçalho (exportações) ────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Exportar Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->url(fn () => route('admin.relatorio.excel', array_filter([
                    'inicio' => $this->dataInicio,
                    'fim' => $this->dataFim,
                    'profissional' => $this->filtroProfissional,
                    'status' => $this->filtroStatus,
                ])))
                ->openUrlInNewTab(),

            Action::make('exportPdf')
                ->label('Exportar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->url(fn () => route('admin.relatorio.pdf', array_filter([
                    'inicio' => $this->dataInicio,
                    'fim' => $this->dataFim,
                    'profissional' => $this->filtroProfissional,
                    'status' => $this->filtroStatus,
                ])))
                ->openUrlInNewTab(),
        ];
    }

    // ─── Filtros ──────────────────────────────────────────────────────────────

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

            Select::make('filtroProfissional')
                ->label('Barbeiro')
                ->options(Profissional::where('ativo', true)->orderBy('nome')->pluck('nome', 'id'))
                ->placeholder('Todos os barbeiros')
                ->live(),

            Select::make('filtroStatus')
                ->label('Status')
                ->options([
                    'todos' => 'Todos',
                    'confirmado' => 'Confirmado',
                    'concluido' => 'Concluído',
                    'pendente' => 'Pendente',
                    'cancelado' => 'Cancelado',
                ])
                ->default('todos')
                ->live(),
        ])->columns(4);
    }

    // ─── Stat cards ───────────────────────────────────────────────────────────

    public function statsSchema(Schema $schema): Schema
    {
        $r = $this->calcResumo();

        // Cada card só entra se o plano do tenant incluir o relatório correspondente
        $cards = [];

        if ($this->temRelatorio('atendimentos')) {
            $cards[] = Stat::make('Atendimentos', (string) $r['total'])
                ->description('no período selecionado')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('warning');
        }

        if ($this->temRelatorio('receita')) {
            $cards[] = Stat::make('Receita Total', 'R$ '.number_format($r['receita'], 2, ',', '.'))
                ->description('ticket médio R$ '.number_format($r['ticketMed'], 2, ',', '.'))
                ->icon(Heroicon::OutlinedCurrencyDollar)
                ->color('success');
        }

        if ($this->temRelatorio('desempenho_barbeiro')) {
            $cards[] = Stat::make('Comissões a Pagar', 'R$ '.number_format($r['comissoes'], 2, ',', '.'))
                ->description('total devido aos barbeiros')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('info');
        }

        if ($this->temRelatorio('clientes_unicos')) {
            $cards[] = Stat::make('Clientes Únicos', (string) $r['unicos'])
                ->description($r['recorrentes'].' retornaram no trimestre')
                ->icon(Heroicon::OutlinedUsers)
                ->color('info');
        }

        if ($this->temRelatorio('cancelamentos')) {
            $cards[] = Stat::make('Cancelamentos', $r['taxaCancel'].'%')
                ->description($r['cancelados'].' agendamentos cancelados')
                ->icon(Heroicon::OutlinedXCircle)
                ->color($r['taxaCancel'] > 20 ? 'danger' : 'gray');
        }

        if ($this->temRelatorio('servico_top')) {
            $cards[] = Stat::make('Serviço + Realizado', $r['servicoTop']['nome'] ?? '—')
                ->description(($r['servicoTop']['qtd'] ?? 0).'x no período')
                ->icon(Heroicon::OutlinedScissors)
                ->color('warning');
        }

        return $schema->components($cards)->columns(3);
    }

    // ─── Tabela de desempenho por barbeiro ────────────────────────────────────

    public function table(Table $table): Table
    {
        $inicio = $this->dataInicio ?: now()->startOfMonth()->format('Y-m-d');
        $fim = $this->dataFim ?: now()->endOfMonth()->format('Y-m-d');
        $pid = $this->filtroProfissional ? (int) $this->filtroProfissional : null;

        $statsMap = $this->buildBarbeiroMap($inicio, $fim, $pid);
        $totalAtend = array_sum(array_column($statsMap, 'total'));

        return $table
            ->query(
                Profissional::query()
                    ->where('ativo', true)
                    ->when($pid, fn ($q) => $q->where('id', $pid))
                    ->orderBy('nome')
            )
            ->heading('Desempenho por Barbeiro')
            ->description('Período: '.Carbon::parse($inicio)->format('d/m/Y').' a '.Carbon::parse($fim)->format('d/m/Y'))
            ->columns([
                TextColumn::make('nome')
                    ->label('Barbeiro')
                    ->weight(FontWeight::SemiBold),

                TextColumn::make('atendimentos')
                    ->label('Atendimentos')
                    ->getStateUsing(fn ($record) => $statsMap[$record->id]['total'] ?? 0)
                    ->badge()
                    ->color('warning')
                    ->alignCenter(),

                TextColumn::make('participacao')
                    ->label('Participação')
                    ->getStateUsing(fn ($record) => $totalAtend > 0
                        ? round((($statsMap[$record->id]['total'] ?? 0) / $totalAtend) * 100, 1).'%'
                        : '0%')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),

                TextColumn::make('receita')
                    ->label('Receita')
                    ->getStateUsing(fn ($record) => 'R$ '.number_format($statsMap[$record->id]['receita'] ?? 0, 2, ',', '.'))
                    ->color('success')
                    ->weight(FontWeight::SemiBold)
                    ->alignEnd(),

                TextColumn::make('ticket')
                    ->label('Ticket Médio')
                    ->getStateUsing(fn ($record) => 'R$ '.number_format($statsMap[$record->id]['ticket'] ?? 0, 2, ',', '.'))
                    ->alignEnd(),

                TextColumn::make('comissao')
                    ->label('Comissão')
                    ->getStateUsing(fn ($record) => 'R$ '.number_format($statsMap[$record->id]['comissao'] ?? 0, 2, ',', '.').' ('.($statsMap[$record->id]['perc'] ?? 0).'%)')
                    ->color('warning')
                    ->alignEnd(),

                TextColumn::make('clientes_unicos')
                    ->label('Clientes')
                    ->getStateUsing(fn ($record) => $statsMap[$record->id]['clientes'] ?? 0)
                    ->alignCenter(),
            ])
            ->striped()
            ->paginated(false);
    }

    // ─── Helpers privados ─────────────────────────────────────────────────────

    private function statusFiltrados(): array
    {
        return match ($this->filtroStatus) {
            'confirmado' => ['confirmado'],
            'concluido' => ['concluido'],
            'pendente' => ['pendente'],
            'cancelado' => ['cancelado'],
            default => ['confirmado', 'concluido'],
        };
    }

    private function buildBarbeiroMap(string $inicio, string $fim, ?int $pid): array
    {
        $ags = Agendamento::whereIn('status', $this->statusFiltrados())
            ->whereBetween('data_hora', [$inicio.' 00:00:00', $fim.' 23:59:59'])
            ->with(['servico'])
            ->get();

        $map = [];
        foreach (Profissional::where('ativo', true)->get() as $prof) {
            $profAgs = $ags->where('profissional_id', $prof->id);
            $receita = $profAgs->sum(fn ($a) => $a->valor_total ?? $a->servico?->preco ?? 0);
            $total = $profAgs->count();
            $percProf = (float) ($prof->comissao_percentual ?? 0);
            $map[$prof->id] = [
                'total' => $total,
                'receita' => $receita,
                'ticket' => $total > 0 ? $receita / $total : 0,
                'comissao' => round($receita * ($percProf / 100), 2),
                'clientes' => $profAgs->pluck('cliente_telefone')->unique()->count(),
                'perc' => $percProf,
            ];
        }

        return $map;
    }

    private function calcResumo(): array
    {
        $inicio = $this->dataInicio ?: now()->startOfMonth()->format('Y-m-d');
        $fim = $this->dataFim ?: now()->endOfMonth()->format('Y-m-d');
        $pid = $this->filtroProfissional ? (int) $this->filtroProfissional : null;

        $statuses = $this->statusFiltrados();

        $ags = Agendamento::whereIn('status', $statuses)
            ->whereBetween('data_hora', [$inicio.' 00:00:00', $fim.' 23:59:59'])
            ->when($pid, fn ($q) => $q->where('profissional_id', $pid))
            ->with(['servico', 'profissional', 'servicos'])
            ->get();

        $receita = $ags->sum(fn ($a) => $a->valor_total ?? $a->servico?->preco ?? 0);
        $total = $ags->count();
        $ticketMed = $total > 0 ? $receita / $total : 0;
        $unicos = $ags->pluck('cliente_telefone')->unique()->count();

        $comissoes = 0;
        foreach ($ags->groupBy('profissional_id') as $profId => $profAgs) {
            $prof = $profAgs->first()->profissional;
            $percProf = (float) ($prof?->comissao_percentual ?? 0);
            $receitaProf = $profAgs->sum(fn ($a) => $a->valor_total ?? $a->servico?->preco ?? 0);
            $comissoes += round($receitaProf * ($percProf / 100), 2);
        }

        $cancelados = Agendamento::where('status', 'cancelado')
            ->whereBetween('data_hora', [$inicio.' 00:00:00', $fim.' 23:59:59'])
            ->when($pid, fn ($q) => $q->where('profissional_id', $pid))
            ->count();

        $totalCriados = Agendamento::whereBetween('data_hora', [$inicio.' 00:00:00', $fim.' 23:59:59'])
            ->when($pid, fn ($q) => $q->where('profissional_id', $pid))
            ->count();

        $taxaCancel = $totalCriados > 0 ? round(($cancelados / $totalCriados) * 100, 1) : 0;

        // Conta TODOS os serviços de cada agendamento (multi-serviço) — pivot com
        // fallback para o servico_id em agendamentos antigos sem pivot
        $servicoTop = $ags->flatMap(fn ($a) => $a->servicos->isNotEmpty() ? $a->servicos : collect([$a->servico]))
            ->filter()
            ->groupBy('id')
            ->map(fn ($g) => ['nome' => $g->first()->nome ?? '?', 'qtd' => $g->count()])
            ->sortByDesc('qtd')->first();

        $recorrentes = Agendamento::whereIn('status', ['confirmado', 'concluido'])
            ->where('data_hora', '>=', now()->subMonths(3))
            ->when($pid, fn ($q) => $q->where('profissional_id', $pid))
            ->select('cliente_telefone', DB::raw('count(*) as total'))
            ->groupBy('cliente_telefone')
            ->having('total', '>', 1)
            ->count();

        return compact('total', 'receita', 'ticketMed', 'unicos', 'comissoes',
            'cancelados', 'taxaCancel', 'servicoTop', 'recorrentes');
    }
}
