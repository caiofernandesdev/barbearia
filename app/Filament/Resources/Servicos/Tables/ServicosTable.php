<?php

namespace App\Filament\Resources\Servicos\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

class ServicosTable
{
    public static function configure(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $emGrade = method_exists($livewire, 'emGrade') && $livewire->emGrade();

        $table
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

        return $emGrade
            ? self::configurarGrade($table)
            : self::configurarLista($table);
    }

    /** Layout tradicional em linhas. */
    protected static function configurarLista(Table $table): Table
    {
        return $table
            ->contentGrid(null)
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
            ]);
    }

    /** Layout em cards (grade responsiva). */
    protected static function configurarGrade(Table $table): Table
    {
        return $table
            ->contentGrid(['default' => 1, 'sm' => 2, 'lg' => 3, 'xl' => 4])
            ->columns([
                Stack::make([
                    ImageColumn::make('foto')
                        ->height(140)
                        ->extraImgAttributes(['class' => 'w-full object-cover rounded-lg'])
                        ->defaultImageUrl(null),

                    TextColumn::make('nome')
                        ->weight('bold')
                        ->searchable()
                        ->size('lg'),

                    TextColumn::make('preco')
                        ->money('BRL')
                        ->color('primary')
                        ->weight('semibold'),

                    TextColumn::make('duracao_minutos')
                        ->suffix(' min')
                        ->icon('heroicon-m-clock')
                        ->color('gray'),

                    TextColumn::make('status_badge')
                        ->badge()
                        ->getStateUsing(fn ($record) => $record->ativo ? 'Ativo' : 'Inativo')
                        ->color(fn ($record) => $record->ativo ? 'success' : 'danger'),
                ])
                    ->space(2),
            ]);
    }
}
