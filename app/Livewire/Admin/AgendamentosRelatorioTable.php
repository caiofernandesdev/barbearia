<?php

namespace App\Livewire\Admin;

use App\Filament\Support\AgendamentoTabela;
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

    public string $dataInicio = '';

    public string $dataFim = '';

    public ?string $filtroProfissional = null;

    public ?string $filtroStatus = null;

    public function table(Table $table): Table
    {
        $inicio = $this->dataInicio ?: now()->startOfMonth()->format('Y-m-d');
        $fim = $this->dataFim ?: now()->endOfMonth()->format('Y-m-d');
        $pid = $this->filtroProfissional ? (int) $this->filtroProfissional : null;

        $statuses = match ($this->filtroStatus) {
            'confirmado' => ['confirmado'],
            'concluido' => ['concluido'],
            'pendente' => ['pendente'],
            'cancelado' => ['cancelado'],
            default => ['pendente', 'confirmado', 'concluido', 'cancelado'],
        };

        return $table
            ->query(
                Agendamento::query()
                    ->whereBetween('data_hora', [$inicio.' 00:00:00', $fim.' 23:59:59'])
                    ->whereIn('status', $statuses)
                    ->when($pid, fn ($q) => $q->where('profissional_id', $pid))
                    ->with(['servico', 'servicos', 'profissional'])
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
                    ->label('Profissional')
                    ->sortable(),

                TextColumn::make('servico.nome')
                    ->label('Serviço')
                    ->getStateUsing(fn ($record) => $record->nomesServicos()),

                TextColumn::make('valor_total')
                    ->label('Valor')
                    ->getStateUsing(fn ($record) => $record->valor_total ?? $record->servico?->preco ?? 0)
                    ->formatStateUsing(fn ($state) => 'R$ '.number_format((float) ($state ?? 0), 2, ',', '.'))
                    ->color('success')
                    ->alignEnd(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'concluido' => 'success',
                        'confirmado' => 'info',
                        'cancelado' => 'danger',
                        'pendente' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'concluido' => 'Concluído',
                        'confirmado' => 'Confirmado',
                        'cancelado' => 'Cancelado',
                        'pendente' => 'Pendente',
                        default => ucfirst($state),
                    })
                    ->alignCenter(),

                // Respostas dos campos personalizados (visível por padrão no relatório)
                AgendamentoTabela::colunaDetalhes(ocultaPorPadrao: false),
            ])
            ->filters([
                ...AgendamentoTabela::filtrosCamposExtras(),
            ])
            ->striped()
            ->defaultPaginationPageOption(15);
    }

    public function render()
    {
        return view('livewire.admin.agendamentos-relatorio-table');
    }
}
