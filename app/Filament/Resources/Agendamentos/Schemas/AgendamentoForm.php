<?php

namespace App\Filament\Resources\Agendamentos\Schemas;

use App\Models\Profissional;
use App\Models\Servico;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AgendamentoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('cliente_nome')
                ->label('Nome do Cliente')
                ->required()
                ->maxLength(100),

            TextInput::make('cliente_telefone')
                ->label('Telefone')
                ->required()
                ->tel()
                ->maxLength(20),

            Select::make('profissional_id')
                ->label('Profissional')
                ->required()
                ->options(Profissional::where('ativo', true)->pluck('nome', 'id')),

            Select::make('servico_id')
                ->label('Serviço')
                ->required()
                ->options(Servico::where('ativo', true)->orderBy('ordem')->pluck('nome', 'id')),

            DateTimePicker::make('data_hora')
                ->label('Data e Hora')
                ->required()
                ->seconds(false),

            Select::make('status')
                ->label('Status')
                ->required()
                ->options([
                    'pendente' => 'Pendente',
                    'confirmado' => 'Confirmado',
                    'concluido' => 'Concluído',
                    'cancelado' => 'Cancelado',
                ])
                ->default('pendente'),

            Toggle::make('mensalista')
                ->label('Mensalista')
                ->default(false),

            Textarea::make('observacao')
                ->label('Observação')
                ->nullable()
                ->rows(3),
        ]);
    }
}
