<?php

namespace App\Filament\Resources\Mensalistas\Tables;

use App\Filament\Pages\AgendaFixa;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MensalistasTable
{
    public static function configure(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $emGrade = method_exists($livewire, 'emGrade') && $livewire->emGrade();

        $table
            ->filters([
                SelectFilter::make('tipo')
                    ->options([
                        'avulso' => __('painel.cliente.f_avulso'),
                        'mensalista' => __('painel.cliente.f_mensalista'),
                        'mensalista_fixo' => __('painel.cliente.f_fixo'),
                    ]),
            ])
            ->actions([
                Action::make('agenda_fixa')
                    ->label(__('painel.cliente.act_agenda_fixa'))
                    ->icon('heroicon-o-calendar-days')
                    ->color('warning')
                    ->visible(fn () => (app()->bound('current_tenant') ? app('current_tenant')?->hasFeature('agenda_fixa') : false) ?? false)
                    ->url(fn ($record) => AgendaFixa::getUrl(['mensalista' => $record->id])),

                EditAction::make(),
            ]);

        return $emGrade
            ? self::configurarGrade($table)
            : self::configurarLista($table);
    }

    /** Badge do tipo — reaproveitado nos dois layouts. */
    private static function colunaTipo(): TextColumn
    {
        return TextColumn::make('tipo')
            ->label(__('painel.cliente.col_tipo'))
            ->badge()
            ->color(fn (string $state): string => match ($state) {
                'mensalista_fixo' => 'warning',
                'mensalista' => 'info',
                default => 'gray',
            })
            ->formatStateUsing(fn (string $state): string => match ($state) {
                'mensalista_fixo' => __('painel.cliente.badge_fixo'),
                'mensalista' => __('painel.cliente.badge_mensalista'),
                default => __('painel.cliente.badge_avulso'),
            });
    }

    /** Layout em linhas — colunas secundárias somem no mobile. */
    protected static function configurarLista(Table $table): Table
    {
        return $table
            ->contentGrid(null)
            ->columns([
                TextColumn::make('nome')
                    ->label(__('painel.cliente.nome'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('telefone')
                    ->label(__('painel.cliente.col_telefone'))
                    ->searchable()
                    ->visibleFrom('sm'),

                self::colunaTipo(),

                TextColumn::make('limite_cortes_semana')
                    ->label(__('painel.cliente.col_limite'))
                    ->alignCenter()
                    ->visibleFrom('md'),

                TextColumn::make('horariosFixos_count')
                    ->counts('horariosFixos')
                    ->label(__('painel.cliente.col_horarios_fixos'))
                    ->alignCenter()
                    ->visibleFrom('lg'),

                TextColumn::make('updated_at')
                    ->label(__('painel.cliente.col_atualizado'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->visibleFrom('lg'),
            ]);
    }

    /** Layout em cards (grade responsiva). */
    protected static function configurarGrade(Table $table): Table
    {
        return $table
            ->contentGrid(['default' => 1, 'sm' => 2, 'lg' => 3, 'xl' => 4])
            ->columns([
                Stack::make([
                    TextColumn::make('nome')
                        ->searchable()
                        ->weight('bold')
                        ->size('lg'),

                    self::colunaTipo(),

                    TextColumn::make('telefone')
                        ->placeholder(__('painel.cliente.col_sem_telefone'))
                        ->icon('heroicon-m-device-phone-mobile')
                        ->color('gray'),

                    TextColumn::make('limite_cortes_semana')
                        ->prefix(__('painel.cliente.grade_limite_prefix'))
                        ->suffix(__('painel.cliente.grade_limite_suffix'))
                        ->icon('heroicon-m-scissors')
                        ->color('gray'),
                ])
                    ->space(2),
            ]);
    }
}
