<?php

namespace App\Filament\Resources\Servicos\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ServicoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nome')
                ->label('Nome')
                ->required()
                ->maxLength(100),

            FileUpload::make('foto')
                ->label('Imagem do Serviço')
                ->image()
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->imageResizeMode('contain')
                ->imageResizeTargetWidth('1200')
                ->imageResizeTargetHeight('1200')
                ->maxSize(10240)
                ->directory('servicos')
                ->imagePreviewHeight('120')
                ->nullable()
                ->helperText('Exibida no chatbot de agendamento ao lado do serviço.'),

            TextInput::make('preco')
                ->label('Preço (R$)')
                ->numeric()
                ->required()
                ->prefix('R$')
                ->minValue(0),

            TextInput::make('duracao_minutos')
                ->label('Duração (minutos)')
                ->numeric()
                ->required()
                ->minValue(5)
                ->suffix('min'),

            TextInput::make('ordem')
                ->label('Ordem de exibição')
                ->numeric()
                ->default(0),

            Toggle::make('destaque')
                ->label('Destaque')
                ->default(false),

            Toggle::make('ativo')
                ->label('Ativo')
                ->default(true),
        ]);
    }
}
