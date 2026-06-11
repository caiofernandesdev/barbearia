<?php

namespace App\Livewire\Admin;

use App\Models\Agendamento;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;

class AgendamentosRelatorioTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public string  $dataInicio        = '';
    public string  $dataFim           = '';
    public ?string $filtroProfissional = null;

    public function table(Table $table): Table
    {
        $inicio = $this->dataInicio ?: now()->startOfMonth()->format('Y-m-d');
        $fim    = $this->dataFim    ?: now()->endOfMonth()->format('Y-m-d');
        $pid    = $this->filtroProfissional ? (int) $this->filtroProfissional : null;

        return $table
            ->query(
                Agendamento::query()
                    ->whereBetween('data_hora', [$inicio . ' 00:00:00', $fim . ' 23:59:59'])
                    ->whereIn('status', ['confirmado', 'concluido', 'cancelado'])
                    ->when($pid, fn ($q) => $q->where('profissional_id', $pid))
                    ->with(['servico', 'profissional'])
                    ->latest('data_hora')
            )
            ->heading('Agendamentos do Período')
            ->columns([
                TextColumn::make('data_hora')
                    ->label('Data / Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('cliente_nome')
                    ->label('Cliente')
                    ->weight(FontWeight::SemiBold)
                    ->description(fn ($record) => $record->cliente_telefone)
                    ->searchable(),

                TextColumn::make('profissional.nome')
                    ->label('Barbeiro')
                    ->sortable(),

                TextColumn::make('servico.nome')
                    ->label('Serviço'),

                TextColumn::make('servico.preco')
                    ->label('Valor')
                    ->formatStateUsing(fn ($state) => 'R$ ' . number_format((float) ($state ?? 0), 2, ',', '.'))
                    ->color('success')
                    ->alignEnd(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'concluido'  => 'success',
                        'confirmado' => 'info',
                        'cancelado'  => 'danger',
                        'pendente'   => 'warning',
                        default      => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'concluido'  => 'Concluído',
                        'confirmado' => 'Confirmado',
                        'cancelado'  => 'Cancelado',
                        'pendente'   => 'Pendente',
                        default      => ucfirst($state),
                    })
                    ->alignCenter(),
            ])
            ->striped()
            ->defaultPaginationPageOption(15);
    }

    public function render()
    {
        return view('livewire.admin.agendamentos-relatorio-table');
    }
}
