<?php

namespace App\Livewire\Admin;

use App\Models\Agendamento;
use Carbon\Carbon;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class EvolucaoMensalTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public ?string $filtroProfissional = null;

    public function table(Table $table): Table
    {
        $pid = $this->filtroProfissional ? (int) $this->filtroProfissional : null;

        return $table
            ->query(
                Agendamento::query()
                    ->select([
                        DB::raw('MIN(agendamentos.id) as id'),
                        DB::raw('DATE_FORMAT(agendamentos.data_hora, "%Y-%m") as mes_ano'),
                        DB::raw('COUNT(agendamentos.id) as total_atendimentos'),
                        DB::raw('COALESCE(SUM(servicos.preco), 0) as receita_total'),
                    ])
                    ->leftJoin('servicos', 'servicos.id', '=', 'agendamentos.servico_id')
                    ->whereIn('agendamentos.status', ['confirmado', 'concluido'])
                    ->where('agendamentos.data_hora', '>=', now()->subMonths(5)->startOfMonth())
                    ->when($pid, fn ($q) => $q->where('agendamentos.profissional_id', $pid))
                    ->groupByRaw('DATE_FORMAT(agendamentos.data_hora, "%Y-%m")')
                    ->orderByRaw('DATE_FORMAT(agendamentos.data_hora, "%Y-%m") ASC')
            )
            ->heading('Evolução Mensal — últimos 6 meses')
            ->columns([
                TextColumn::make('mes_ano')
                    ->label('Mês')
                    ->formatStateUsing(fn ($state) => Carbon::createFromFormat('Y-m', $state)->locale('pt_BR')->isoFormat('MMM/YY'))
                    ->weight(FontWeight::SemiBold),

                TextColumn::make('total_atendimentos')
                    ->label('Atendimentos')
                    ->badge()
                    ->color('warning')
                    ->alignCenter(),

                TextColumn::make('receita_total')
                    ->label('Receita')
                    ->formatStateUsing(fn ($state) => 'R$ ' . number_format((float) $state, 2, ',', '.'))
                    ->color('success')
                    ->weight(FontWeight::SemiBold)
                    ->alignEnd(),

                TextColumn::make('ticket_medio')
                    ->label('Ticket Médio')
                    ->getStateUsing(fn ($record) => 'R$ ' . number_format(
                        $record->total_atendimentos > 0
                            ? $record->receita_total / $record->total_atendimentos
                            : 0,
                        2, ',', '.'
                    ))
                    ->alignEnd(),
            ])
            ->striped()
            ->paginated(false)
            ->defaultKeySort(false);
    }

    public function render()
    {
        return view('livewire.admin.evolucao-mensal-table');
    }
}
