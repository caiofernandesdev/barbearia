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
                        'avulso' => 'Avulso',
                        'mensalista' => 'Mensalista',
                        'mensalista_fixo' => 'Mensalista Fixo',
                    ]),
            ])
            ->actions([
                Action::make('agenda_fixa')
                    ->label('Agenda Fixa')
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
            ->label('Tipo')
            ->badge()
            ->color(fn (string $state): string => match ($state) {
                'mensalista_fixo' => 'warning',
                'mensalista' => 'info',
                default => 'gray',
            })
            ->formatStateUsing(fn (string $state): string => match ($state) {
                'mensalista_fixo' => 'Fixo',
                'mensalista' => 'Mensalista',
                default => 'Avulso',
            });
    }

    /** Layout em linhas — colunas secundárias somem no mobile. */
    protected static function configurarLista(Table $table): Table
    {
        return $table
            ->contentGrid(null)
            ->columns([
                TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('telefone')
                    ->label('Telefone')
                    ->searchable()
                    ->visibleFrom('sm'),

                self::colunaTipo(),

                TextColumn::make('limite_cortes_semana')
                    ->label('Limite/semana')
                    ->alignCenter()
                    ->visibleFrom('md'),

                TextColumn::make('horariosFixos_count')
                    ->counts('horariosFixos')
                    ->label('Horários fixos')
                    ->alignCenter()
                    ->visibleFrom('lg'),

                TextColumn::make('updated_at')
                    ->label('Atualizado')
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
                        ->placeholder('sem telefone')
                        ->icon('heroicon-m-device-phone-mobile')
                        ->color('gray'),

                    TextColumn::make('limite_cortes_semana')
                        ->prefix('Limite: ')
                        ->suffix('/semana')
                        ->icon('heroicon-m-scissors')
                        ->color('gray'),
                ])
                    ->space(2),
            ]);
    }
}
