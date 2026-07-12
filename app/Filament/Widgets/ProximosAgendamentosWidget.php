<?php

namespace App\Filament\Widgets;

use App\Filament\Support\AgendamentoTabela;
use App\Models\Agendamento;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ProximosAgendamentosWidget extends TableWidget
{
    protected static ?int $sort = 5;

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Próximos Agendamentos')
            ->query(
                Agendamento::query()
                    ->whereIn('status', ['pendente', 'confirmado'])
                    ->where('data_hora', '>=', now())
                    ->with(['profissional', 'servico'])
                    ->orderBy('data_hora')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('data_hora')
                    ->label('Quando')
                    ->dateTime('d/m H:i')
                    ->weight(FontWeight::SemiBold)
                    ->description(fn ($record) => $record->data_hora->isToday() ? 'Hoje' : ($record->data_hora->isTomorrow() ? 'Amanhã' : $record->data_hora->locale('pt_BR')->diffForHumans())),

                TextColumn::make('cliente_nome')
                    ->label('Cliente')
                    ->searchable()
                    ->description(fn ($record) => $record->cliente_telefone),

                TextColumn::make('profissional.nome')
                    ->label('Profissional'),

                TextColumn::make('servico.nome')
                    ->label('Serviço')
                    ->getStateUsing(fn ($record) => $record->nomesServicos())
                    ->description(fn ($record) => 'R$ '.number_format((float) ($record->valor_total ?? $record->servico?->preco ?? 0), 2, ',', '.')),

                AgendamentoTabela::colunaDetalhes(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'pendente' => 'warning',
                        'confirmado' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pendente' => 'Pendente',
                        'confirmado' => 'Confirmado',
                        default => ucfirst($state),
                    }),
            ])
            ->paginated(false)
            ->striped();
    }
}
