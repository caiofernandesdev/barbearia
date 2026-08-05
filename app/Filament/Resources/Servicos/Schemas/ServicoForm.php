<?php

namespace App\Filament\Resources\Servicos\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
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
                ->minValue(0)
                ->helperText('Menor número aparece primeiro. Deixe em branco para o serviço ir para o fim.'),

            Section::make('Preço por dia da semana')
                ->description('Opcional. Preencha só os dias com valor diferente — os demais usam o preço acima.')
                ->collapsed()
                ->columns(2)
                ->schema(
                    collect([
                        1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta',
                        5 => 'Sexta', 6 => 'Sábado', 0 => 'Domingo',
                    ])->map(fn (string $label, int $dia) => TextInput::make("precos_por_dia.{$dia}")
                        ->label($label)
                        ->numeric()
                        ->prefix('R$')
                        ->minValue(0)
                        ->placeholder('usa o preço base')
                    )->values()->all()
                ),

            Toggle::make('destaque')
                ->label('Destaque')
                ->default(false),

            Toggle::make('ativo')
                ->label('Ativo')
                ->default(true),
        ]);
    }
}
