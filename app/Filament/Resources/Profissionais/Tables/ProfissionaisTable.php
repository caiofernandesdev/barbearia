<?php

namespace App\Filament\Resources\Profissionais\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

class ProfissionaisTable
{
    public static function configure(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $emGrade = method_exists($livewire, 'emGrade') && $livewire->emGrade();

        $table
            ->filters([
                Filter::make('ativos')
                    ->label(__('painel.profissional.f_ativos'))
                    ->query(fn ($query) => $query->where('ativo', true)),
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
                ImageColumn::make('foto')
                    ->label(__('painel.profissional.col_foto'))
                    ->circular(),

                TextColumn::make('nome')
                    ->label(__('painel.profissional.nome'))
                    ->searchable()
                    ->sortable(),

                // Sem telefone o profissional não recebe nenhum aviso por WhatsApp —
                // sinaliza para o dono não descobrir isso só quando faltar mensagem
                TextColumn::make('telefone')
                    ->label(__('painel.profissional.col_whatsapp'))
                    ->placeholder(__('painel.profissional.col_sem_telefone'))
                    ->badge()
                    ->color(fn ($state) => filled($state) ? 'gray' : 'warning')
                    ->icon(fn ($state) => filled($state) ? null : 'heroicon-o-exclamation-triangle'),

                TextColumn::make('limite_mensalistas')
                    ->label(__('painel.profissional.col_limite'))
                    ->sortable(),

                TextColumn::make('agendamentos_count')
                    ->label(__('painel.profissional.col_agendamentos'))
                    ->counts('agendamentos')
                    ->sortable(),

                IconColumn::make('ativo')
                    ->label(__('painel.profissional.col_ativo'))
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label(__('painel.profissional.col_cadastrado'))
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
                        ->height(96)
                        ->width(96)
                        ->circular(),

                    TextColumn::make('nome')
                        ->weight('bold')
                        ->searchable()
                        ->size('lg'),

                    TextColumn::make('telefone')
                        ->placeholder(__('painel.profissional.col_sem_whatsapp'))
                        ->icon('heroicon-m-device-phone-mobile')
                        ->color(fn ($state) => filled($state) ? 'gray' : 'warning'),

                    TextColumn::make('agendamentos_count')
                        ->counts('agendamentos')
                        ->suffix(__('painel.profissional.col_ag_suffix'))
                        ->icon('heroicon-m-calendar-days')
                        ->color('gray'),

                    TextColumn::make('status_badge')
                        ->badge()
                        ->getStateUsing(fn ($record) => $record->ativo ? __('painel.profissional.ativo') : __('painel.profissional.inativo'))
                        ->color(fn ($record) => $record->ativo ? 'success' : 'danger'),
                ])
                    ->space(2),
            ]);
    }
}
