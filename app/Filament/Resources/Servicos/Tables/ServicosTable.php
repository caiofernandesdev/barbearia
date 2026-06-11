<?php

namespace App\Filament\Resources\Servicos\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

class ServicosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ordem')
                    ->label('#')
                    ->sortable(),

                ImageColumn::make('foto')
                    ->label('Foto')
                    ->circular()
                    ->defaultImageUrl(null)
                    ->toggleable(),

                TextColumn::make('nome')
                    ->label('Serviço')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('preco')
                    ->label('Preço')
                    ->money('BRL')
                    ->sortable(),

                TextColumn::make('duracao_minutos')
                    ->label('Duração')
                    ->suffix(' min')
                    ->sortable(),

                IconColumn::make('destaque')
                    ->label('Destaque')
                    ->boolean(),

                IconColumn::make('ativo')
                    ->label('Ativo')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Cadastrado em')
                    ->date('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('ordem')
            ->filters([
                Filter::make('ativos')
                    ->label('Apenas ativos')
                    ->query(fn ($query) => $query->where('ativo', true)),

                Filter::make('destaques')
                    ->label('Em destaque')
                    ->query(fn ($query) => $query->where('destaque', true)),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->label('Excluir'),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
