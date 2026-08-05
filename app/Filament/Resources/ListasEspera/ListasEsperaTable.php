<?php

namespace App\Filament\Resources\ListasEspera;

use App\Models\Agendamento;
use App\Models\ConfiguracaoBarbearia;
use App\Services\DisponibilidadeService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ListasEsperaTable
{
    public static function configure(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $emGrade = method_exists($livewire, 'emGrade') && $livewire->emGrade();

        $table
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
                    ->modalHeading('Encaixar na agenda')
                    ->modalSubmitActionLabel('Encaixar')
                    ->visible(fn ($record) => $record->status === 'aguardando')
                    // O dono escolhe qualquer horário livre do profissional nesse dia
                    ->schema([
                        Select::make('hora')
                            ->label('Horário')
                            ->options(fn ($record) => self::horariosLivres($record))
                            ->default(fn ($record) => $record->hora_preferida)
                            ->helperText(fn ($record) => "Cliente pediu {$record->hora_preferida}. Escolha o horário livre para encaixar.")
                            ->required(),
                    ])
                    ->action(function (array $data, $record) {
                        $inicio = Carbon::parse($record->data->format('Y-m-d').' '.$data['hora']);
                        $duracao = (int) ($record->servico?->duracao_minutos ?? 30);

                        if (Agendamento::temConflito((int) $record->profissional_id, $inicio, $duracao, $record->tenant_id)) {
                            Notification::make()
                                ->title('Horário ocupado')
                                ->body('Esse horário já tem outro atendimento. Escolha outro.')
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

        return $emGrade
            ? self::configurarGrade($table)
            : self::configurarLista($table);
    }

    /** Badge do status — reaproveitado nos dois layouts. */
    private static function colunaStatus(): TextColumn
    {
        return TextColumn::make('status')
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
            });
    }

    /** Layout em linhas — colunas secundárias somem no mobile. */
    protected static function configurarLista(Table $table): Table
    {
        return $table
            ->contentGrid(null)
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
                    ->label('Profissional')
                    ->visibleFrom('md'),

                TextColumn::make('servico.nome')
                    ->label('Serviço')
                    ->placeholder('—')
                    ->visibleFrom('md'),

                self::colunaStatus(),

                TextColumn::make('created_at')
                    ->label('Entrou em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ]);
    }

    /** Layout em cards (grade responsiva) — cada pedido vira um card. */
    protected static function configurarGrade(Table $table): Table
    {
        return $table
            ->contentGrid(['default' => 1, 'sm' => 2, 'lg' => 3])
            ->columns([
                Stack::make([
                    TextColumn::make('cliente_nome')
                        ->searchable()
                        ->weight('bold')
                        ->size('lg')
                        ->description(fn ($record) => $record->cliente_telefone),

                    TextColumn::make('data')
                        ->date('d/m/Y')
                        ->prefix('📅 ')
                        ->suffix(fn ($record) => ' às '.$record->hora_preferida)
                        ->color('gray'),

                    TextColumn::make('profissional.nome')
                        ->icon('heroicon-m-user')
                        ->color('gray'),

                    TextColumn::make('servico.nome')
                        ->placeholder('sem serviço definido')
                        ->icon('heroicon-m-scissors')
                        ->color('gray'),

                    self::colunaStatus(),
                ])
                    ->space(2),
            ]);
    }

    /**
     * Horários livres do profissional no dia do pedido (para encaixar).
     * Inclui o horário pedido pelo cliente, se ainda não estiver ocupado.
     */
    private static function horariosLivres($record): array
    {
        $config = ConfiguracaoBarbearia::getInstance();
        $duracao = (int) ($record->servico?->duracao_minutos ?? $config->intervalo_minutos ?? 30);

        $slots = app(DisponibilidadeService::class)->calcular(
            $record->profissional,
            $duracao,
            Carbon::parse($record->data->format('Y-m-d')),
            $config->horario_abertura ?? '08:00',
            $config->horario_encerramento ?? '19:00',
            (int) ($config->intervalo_minutos ?? 60),
        );

        $horas = collect($slots)->pluck('hora');

        // Garante o horário pedido na lista, se estiver livre de conflito
        $pedido = $record->hora_preferida;
        if (! $horas->contains($pedido)) {
            $inicio = Carbon::parse($record->data->format('Y-m-d').' '.$pedido);
            if (! Agendamento::temConflito((int) $record->profissional_id, $inicio, $duracao, $record->tenant_id)) {
                $horas->push($pedido);
            }
        }

        return $horas->unique()->sort()->mapWithKeys(fn ($h) => [$h => $h])->all();
    }
}
