<?php

namespace App\Filament\Resources\Profissionais\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProfissionalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nome')
                ->label('Nome')
                ->required()
                ->maxLength(100),

            TextInput::make('telefone')
                ->label('WhatsApp do profissional')
                ->tel()
                ->required()
                ->maxLength(20)
                ->placeholder('(11) 99999-9999')
                ->helperText('Recebe notificações de novos agendamentos e cancelamentos.'),

            TextInput::make('limite_mensalistas')
                ->label('Limite de Mensalistas')
                ->numeric()
                ->required()
                ->default(10)
                ->minValue(0),

            TextInput::make('comissao_percentual')
                ->label('Comissão (%)')
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->maxValue(100)
                ->suffix('%')
                ->helperText('Percentual sobre a receita de serviços. Usado na Consolidação Financeira do Salário Emocional.'),

            FileUpload::make('foto')
                ->label('Foto')
                ->image()
                // Redimensiona no navegador ANTES do upload — fotos de galeria de
                // celular (5-15MB) estourariam os limites de upload do servidor
                ->imageResizeMode('contain')
                ->imageResizeTargetWidth('1200')
                ->imageResizeTargetHeight('1200')
                ->maxSize(10240)
                ->directory('profissionais')
                ->nullable(),

            Toggle::make('ativo')
                ->label('Ativo')
                ->default(true),

            Section::make('Serviços que realiza')
                ->description('Marque os serviços que este profissional atende. Se nenhum for marcado, ele atende todos os serviços.')
                ->schema([
                    CheckboxList::make('servicos')
                        ->label('')
                        ->relationship('servicos', 'nome')
                        ->columns(3),
                ]),

            Section::make('Dias de Trabalho')
                ->description('Selecione os dias da semana em que este profissional atende.')
                ->schema([
                    CheckboxList::make('dias_trabalho')
                        ->label('')
                        ->options([
                            0 => 'Domingo',
                            1 => 'Segunda-feira',
                            2 => 'Terça-feira',
                            3 => 'Quarta-feira',
                            4 => 'Quinta-feira',
                            5 => 'Sexta-feira',
                            6 => 'Sábado',
                        ])
                        ->columns(4)
                        ->default([1, 2, 3, 4, 5, 6]),
                ]),

            Section::make('Horários Específicos')
                ->description('Opcional. Se selecionados, apenas esses horários serão exibidos para este profissional. Se deixar em branco, o sistema usa todos os slots do intervalo configurado.')
                ->schema([
                    CheckboxList::make('horarios_trabalho')
                        ->label('')
                        ->options(
                            collect(range(6, 23))->flatMap(fn ($h) => [
                                sprintf('%02d:00', $h) => sprintf('%02d:00', $h),
                                sprintf('%02d:30', $h) => sprintf('%02d:30', $h),
                            ])->toArray()
                        )
                        ->columns(6),
                ]),
        ]);
    }
}
