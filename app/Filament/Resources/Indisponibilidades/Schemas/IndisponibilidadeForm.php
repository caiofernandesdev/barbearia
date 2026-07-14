<?php

namespace App\Filament\Resources\Indisponibilidades\Schemas;

use App\Models\Profissional;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IndisponibilidadeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                // 2 colunas no desktop, empilha sozinho no celular
                ->columns(2)
                ->schema([
                    Select::make('profissional_id')
                        ->label('Profissional')
                        ->options(fn () => Profissional::orderBy('nome')->pluck('nome', 'id')->toArray())
                        ->placeholder('Todo o estabelecimento')
                        ->helperText('Deixe em branco para bloquear a agenda de todos.')
                        ->searchable()
                        ->nullable()
                        ->columnSpanFull(),

                    // native() de propósito: no celular abre o seletor do próprio
                    // sistema. O picker JS do Filament fica espremido em tela pequena.
                    DateTimePicker::make('inicio')
                        ->label('Início')
                        ->required()
                        ->seconds(false)
                        ->default(now()->startOfHour()),

                    DateTimePicker::make('fim')
                        ->label('Fim')
                        ->required()
                        ->seconds(false)
                        ->after('inicio')
                        ->default(now()->startOfHour()->addHour()),

                    TextInput::make('motivo')
                        ->label('Motivo')
                        ->placeholder('Ex: Feriado, evento, compromisso pessoal...')
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
