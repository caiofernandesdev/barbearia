<?php

namespace App\Filament\Resources\Indisponibilidades\Schemas;

use App\Models\Profissional;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class IndisponibilidadeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('profissional_id')
                ->label('Profissional')
                ->options(fn () => Profissional::orderBy('nome')->pluck('nome', 'id')->toArray())
                ->placeholder('Toda a barbearia')
                ->searchable()
                ->nullable(),

            DateTimePicker::make('inicio')
                ->label('Início')
                ->required()
                ->seconds(false)
                ->native(false),

            DateTimePicker::make('fim')
                ->label('Fim')
                ->required()
                ->seconds(false)
                ->native(false)
                ->after('inicio'),

            TextInput::make('motivo')
                ->label('Motivo')
                ->placeholder('Ex: Feriado, evento, compromisso pessoal...')
                ->maxLength(255),
        ]);
    }
}
