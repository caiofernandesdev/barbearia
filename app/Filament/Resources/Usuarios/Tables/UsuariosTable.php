<?php

namespace App\Filament\Resources\Usuarios\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsuariosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable(),

                TextColumn::make('role')
                    ->label('Perfil')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'admin'    => 'warning',
                        'barbeiro' => 'info',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'admin'    => 'Admin',
                        'barbeiro' => 'Barbeiro',
                        default    => ucfirst($state),
                    }),

                TextColumn::make('profissional.nome')
                    ->label('Barbeiro vinculado')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->date('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->label('Excluir'),
            ]);
    }
}
