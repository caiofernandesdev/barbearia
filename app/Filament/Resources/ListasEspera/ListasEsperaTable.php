<?php

namespace App\Filament\Resources\ListasEspera;

use App\Models\Agendamento;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ListasEsperaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('data')
                    ->label('Dia desejado')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('hora_preferida')
                    ->label('Horário')
                    ->badge()
                    ->color('warning'),

                TextColumn::make('cliente_nome')
                    ->label('Cliente')
                    ->description(fn ($record) => $record->cliente_telefone)
                    ->searchable(),

                TextColumn::make('profissional.nome')
                    ->label('Profissional'),

                TextColumn::make('servico.nome')
                    ->label('Serviço')
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'aguardando' => 'warning',
                        'encaixado' => 'success',
                        'cancelado' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'aguardando' => 'Aguardando',
                        'encaixado' => 'Encaixado',
                        'cancelado' => 'Cancelado',
                        default => $state,
                    }),

                TextColumn::make('created_at')
                    ->label('Entrou em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('data')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'aguardando' => 'Aguardando',
                        'encaixado' => 'Encaixado',
                        'cancelado' => 'Cancelado',
                    ])
                    ->default('aguardando'),
            ])
            ->recordActions([
                // Cria o agendamento a partir do pedido, se o horário estiver livre
                Action::make('encaixar')
                    ->label('Encaixar')
                    ->icon('heroicon-o-calendar-days')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Encaixar na agenda?')
                    ->modalDescription(fn ($record) => "Criar o agendamento de {$record->cliente_nome} em ".$record->data->format('d/m/Y')." às {$record->hora_preferida}?")
                    ->modalSubmitActionLabel('Encaixar')
                    ->visible(fn ($record) => $record->status === 'aguardando')
                    ->action(function ($record) {
                        $inicio = Carbon::parse($record->data->format('Y-m-d').' '.$record->hora_preferida);
                        $duracao = (int) ($record->servico?->duracao_minutos ?? 30);

                        if (Agendamento::temConflito((int) $record->profissional_id, $inicio, $duracao, $record->tenant_id)) {
                            Notification::make()
                                ->title('Horário ocupado')
                                ->body('Esse horário já tem outro atendimento. Combine outro com o cliente.')
                                ->danger()
                                ->send();

                            return;
                        }

                        Agendamento::create([
                            'cliente_nome' => $record->cliente_nome,
                            'cliente_telefone' => $record->cliente_telefone,
                            'profissional_id' => $record->profissional_id,
                            'servico_id' => $record->servico_id,
                            'data_hora' => $inicio,
                            'tenant_id' => $record->tenant_id,
                        ]);
                        $record->update(['status' => 'encaixado']);

                        Notification::make()->title('Cliente encaixado na agenda!')->success()->send();
                    }),

                DeleteAction::make()->label('Remover'),
            ]);
    }
}
