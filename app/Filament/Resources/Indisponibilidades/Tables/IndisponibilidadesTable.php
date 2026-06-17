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
                    ->label('Escopo')
                    ->badge()
                    ->color(fn ($state) => $state === 'Toda a barbearia' ? 'danger' : 'warning'),

                TextColumn::make('inicio')
                    ->label('Início')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('fim')
                    ->label('Fim')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('motivo')
                    ->label('Motivo')
                    ->placeholder('—')
                    ->limit(50),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('inicio', 'asc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Nenhuma indisponibilidade cadastrada')
            ->emptyStateDescription('Cadastre bloqueios de agenda por feriados, eventos ou compromissos.');
    }
}
