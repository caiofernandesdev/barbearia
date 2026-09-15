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
                        ->label(__('painel.agendamento.profissional'))
                        ->options(fn () => Profissional::orderBy('nome')->pluck('nome', 'id')->toArray())
                        ->placeholder(__('painel.agenda.ind_prof_ph'))
                        ->helperText(__('painel.indisp.prof_help'))
                        ->searchable()
                        ->nullable()
                        ->columnSpanFull(),

                    // native() de propósito: no celular abre o seletor do próprio
                    // sistema. O picker JS do Filament fica espremido em tela pequena.
                    DateTimePicker::make('inicio')
                        ->label(__('painel.agenda.ind_inicio'))
                        ->required()
                        ->seconds(false)
                        ->default(now()->startOfHour()),

                    DateTimePicker::make('fim')
                        ->label(__('painel.agenda.ind_fim'))
                        ->required()
                        ->seconds(false)
                        ->after('inicio')
                        ->default(now()->startOfHour()->addHour()),

                    TextInput::make('motivo')
                        ->label(__('painel.agenda.ind_motivo'))
                        ->placeholder(__('painel.indisp.motivo_ph'))
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
