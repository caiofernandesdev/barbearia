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
                ->directory('profissionais')
                ->nullable(),

            Toggle::make('ativo')
                ->label('Ativo')
                ->default(true),

            Section::make('Horários de Trabalho')
                ->description('Selecione os horários em que este barbeiro atende. Se nenhum for selecionado, o sistema usará todos os slots configurados na barbearia.')
                ->schema([
                    CheckboxList::make('horarios_trabalho')
                        ->label('')
                        ->options(
                            collect(range(6, 23))->flatMap(fn($h) => [
                                sprintf('%02d:00', $h) => sprintf('%02d:00', $h),
                                sprintf('%02d:30', $h) => sprintf('%02d:30', $h),
                            ])->toArray()
                        )
                        ->columns(6)
                        ->helperText('Apenas esses horários serão exibidos na tela de agendamento para este barbeiro.'),
                ]),
        ]);
    }
}
