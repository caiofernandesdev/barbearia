<?php

namespace App\Filament\Resources\Indisponibilidades\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IndisponibilidadesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('escopo')
                    ->label(__('painel.indisp.col_escopo'))
                    ->badge()
                    ->color(fn ($record) => $record->profissional_id ? 'warning' : 'danger'),

                TextColumn::make('inicio')
                    ->label(__('painel.agenda.ind_inicio'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('fim')
                    ->label(__('painel.agenda.ind_fim'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('motivo')
                    ->label(__('painel.agenda.ind_motivo'))
                    ->placeholder('—')
                    ->limit(50),

                TextColumn::make('created_at')
                    ->label(__('painel.indisp.col_criado'))
                    ->dateTime('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('inicio', 'asc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading(__('painel.indisp.empty_heading'))
            ->emptyStateDescription(__('painel.indisp.empty_desc'));
    }
}
