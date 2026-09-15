<?php

namespace App\Filament\Resources\Servicos\Schemas;

use Carbon\Carbon;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ServicoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nome')
                ->label(__('painel.servico.nome'))
                ->required()
                ->maxLength(100),

            FileUpload::make('foto')
                ->label(__('painel.servico.foto'))
                ->image()
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->imageResizeMode('contain')
                ->imageResizeTargetWidth('1200')
                ->imageResizeTargetHeight('1200')
                ->maxSize(10240)
                ->directory('servicos')
                ->imagePreviewHeight('120')
                ->nullable()
                ->helperText(__('painel.servico.foto_help')),

            TextInput::make('preco')
                ->label(__('painel.servico.preco'))
                ->numeric()
                ->required()
                ->prefix('R$')
                ->minValue(0),

            TextInput::make('duracao_minutos')
                ->label(__('painel.servico.duracao'))
                ->numeric()
                ->required()
                ->minValue(5)
                ->suffix('min'),

            TextInput::make('ordem')
                ->label(__('painel.servico.ordem'))
                ->numeric()
                ->minValue(0)
                ->helperText(__('painel.servico.ordem_help')),

            Section::make(__('painel.servico.sec_preco_dia'))
                ->description(__('painel.servico.sec_preco_dia_desc'))
                ->collapsed()
                ->columns(2)
                ->schema(
                    collect([1, 2, 3, 4, 5, 6, 0])->map(fn (int $dia) => TextInput::make("precos_por_dia.{$dia}")
                        ->label(Str::ucfirst(Carbon::now()->startOfWeek(Carbon::SUNDAY)->addDays($dia)->locale(app()->getLocale())->isoFormat('dddd')))
                        ->numeric()
                        ->prefix('R$')
                        ->minValue(0)
                        ->placeholder(__('painel.servico.preco_dia_ph'))
                    )->values()->all()
                ),

            Toggle::make('destaque')
                ->label(__('painel.servico.destaque'))
                ->default(false),

            Toggle::make('ativo')
                ->label(__('painel.servico.ativo'))
                ->default(true),
        ]);
    }
}
