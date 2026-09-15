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
                    ->label(__('painel.usuario.nome'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label(__('painel.usuario.email'))
                    ->searchable(),

                TextColumn::make('role')
                    ->label(__('painel.usuario.perfil'))
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'admin' => 'warning',
                        'barbeiro' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'admin' => __('painel.usuario.badge_admin'),
                        'barbeiro' => __('painel.usuario.badge_barbeiro'),
                        default => ucfirst($state),
                    }),

                TextColumn::make('profissional.nome')
                    ->label(__('painel.usuario.prof_vinculado'))
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('painel.usuario.col_criado'))
                    ->date('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
