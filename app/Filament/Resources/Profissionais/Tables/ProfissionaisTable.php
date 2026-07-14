<?php

namespace App\Filament\Resources\Profissionais\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

class ProfissionaisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('foto')
                    ->label('Foto')
                    ->circular(),

                TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                // Sem telefone o profissional não recebe nenhum aviso por WhatsApp —
                // sinaliza para o dono não descobrir isso só quando faltar mensagem
                TextColumn::make('telefone')
                    ->label('WhatsApp')
                    ->placeholder('sem telefone — não recebe avisos')
                    ->badge()
                    ->color(fn ($state) => filled($state) ? 'gray' : 'warning')
                    ->icon(fn ($state) => filled($state) ? null : 'heroicon-o-exclamation-triangle'),

                TextColumn::make('limite_mensalistas')
                    ->label('Limite Mensalistas')
                    ->sortable(),

                TextColumn::make('agendamentos_count')
                    ->label('Agendamentos')
                    ->counts('agendamentos')
                    ->sortable(),

                IconColumn::make('ativo')
                    ->label('Ativo')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Cadastrado em')
                    ->date('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('ativos')
                    ->label('Apenas ativos')
                    ->query(fn ($query) => $query->where('ativo', true)),
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
