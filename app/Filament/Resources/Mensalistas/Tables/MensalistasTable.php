<?php

namespace App\Filament\Resources\Mensalistas\Tables;

use App\Filament\Pages\AgendaFixa;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MensalistasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('telefone')
                    ->label('Telefone')
                    ->searchable(),

                TextColumn::make('tipo')
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
                    }),

                TextColumn::make('limite_cortes_semana')
                    ->label('Limite/semana')
                    ->alignCenter()
                    ->visible(fn () => true),

                TextColumn::make('horariosFixos_count')
                    ->counts('horariosFixos')
                    ->label('Horários fixos')
                    ->alignCenter(),

                TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
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
                    ->url(fn ($record) => AgendaFixa::getUrl(['mensalista' => $record->id])),

                EditAction::make(),
            ]);
    }
}
