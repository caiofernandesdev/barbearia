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
                    ->label(__('painel.servico.f_ativos'))
                    ->query(fn ($query) => $query->where('ativo', true)),

                Filter::make('destaques')
                    ->label(__('painel.servico.f_destaque'))
                    ->query(fn ($query) => $query->where('destaque', true)),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
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
                    ->label(__('painel.servico.col_ordem'))
                    ->sortable(),

                ImageColumn::make('foto')
                    ->label(__('painel.servico.col_foto'))
                    ->circular()
                    ->defaultImageUrl(null)
                    ->toggleable(),

                TextColumn::make('nome')
                    ->label(__('painel.servico.col_servico'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('preco')
                    ->label(__('painel.servico.col_preco'))
                    ->money('BRL')
                    ->sortable(),

                TextColumn::make('duracao_minutos')
                    ->label(__('painel.servico.col_duracao'))
                    ->suffix(' min')
                    ->sortable(),

                IconColumn::make('destaque')
                    ->label(__('painel.servico.destaque'))
                    ->boolean(),

                IconColumn::make('ativo')
                    ->label(__('painel.servico.ativo'))
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label(__('painel.servico.col_cadastrado'))
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
                        ->getStateUsing(fn ($record) => $record->ativo ? __('painel.servico.ativo') : __('painel.servico.inativo'))
                        ->color(fn ($record) => $record->ativo ? 'success' : 'danger'),
                ])
                    ->space(2),
            ]);
    }
}
