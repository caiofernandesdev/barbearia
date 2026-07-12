<?php

namespace App\Filament\Resources\Mensalistas\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class MensalistaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            TextInput::make('nome')
                ->label('Nome')
                ->required()
                ->maxLength(100),

            TextInput::make('telefone')
                ->label('Telefone (somente números)')
                ->required()
                ->tel()
                ->maxLength(20)
                ->helperText('Use o mesmo número que o cliente informa no agendamento.'),

            Select::make('tipo')
                ->label('Tipo de Cliente')
                ->options([
                    'avulso' => 'Avulso — atendimento esporádico, sem restrição',
                    'mensalista' => 'Mensalista — limite de cortes por semana',
                    'mensalista_fixo' => 'Mensalista Fixo — horário semanal fixo',
                ])
                ->default('avulso')
                ->required()
                ->live()
                ->helperText('Avulso = liberdade total. Mensalista = limite semanal. Fixo = horário recorrente cadastrado.'),

            TextInput::make('limite_cortes_semana')
                ->label('Limite de cortes por semana')
                ->numeric()
                ->default(1)
                ->minValue(1)
                ->maxValue(7)
                ->helperText('Sobrescreve o limite global configurado no estabelecimento.')
                ->visible(fn (Get $get): bool => $get('tipo') === 'mensalista'),

            TextInput::make('valor_mensalidade')
                ->label('Valor da mensalidade (R$)')
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->prefix('R$')
                ->helperText('Valor cobrado mensalmente deste cliente. Usado no cálculo do Salário Emocional.')
                ->visible(fn (Get $get): bool => in_array($get('tipo'), ['mensalista', 'mensalista_fixo'])),

            // Repeater para horários fixos — visível apenas quando tipo = mensalista_fixo
            Repeater::make('horariosFixos')
                ->label('Horários Fixos Semanais')
                ->relationship()
                ->schema([
                    Select::make('profissional_id')
                        ->label('Profissional')
                        ->relationship('profissional', 'nome')
                        ->required(),

                    Select::make('servico_id')
                        ->label('Serviço')
                        ->relationship('servico', 'nome')
                        ->required(),

                    Select::make('dia_semana')
                        ->label('Dia da Semana')
                        ->options([
                            0 => 'Domingo',
                            1 => 'Segunda-feira',
                            2 => 'Terça-feira',
                            3 => 'Quarta-feira',
                            4 => 'Quinta-feira',
                            5 => 'Sexta-feira',
                            6 => 'Sábado',
                        ])
                        ->required(),

                    Select::make('hora')
                        ->label('Horário')
                        ->options(
                            collect(range(6, 22))->flatMap(fn ($h) => [
                                sprintf('%02d:00', $h) => sprintf('%02d:00', $h),
                                sprintf('%02d:30', $h) => sprintf('%02d:30', $h),
                            ])->prepend('Selecione', '')->toArray()
                        )
                        ->required(),

                    Toggle::make('ativo')
                        ->label('Ativo')
                        ->default(true),
                ])
                ->columns(2)
                ->addActionLabel('+ Adicionar horário fixo')
                ->helperText('Esses horários são bloqueados na agenda para outros clientes e exibidos ao mensalista fixo ao identificar o telefone.')
                ->visible(fn (Get $get): bool => $get('tipo') === 'mensalista_fixo'),

        ]);
    }
}
