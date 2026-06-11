<?php

namespace App\Filament\Resources\ConfiguracoesBarbearia\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ConfiguracaoBarbeariaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Identidade')
                ->columns(2)
                ->schema([
                    TextInput::make('nome_barbearia')
                        ->label('Nome da Barbearia')
                        ->required()
                        ->maxLength(100)
                        ->helperText('Aparece no cabeçalho e nas páginas públicas de agendamento.'),

                    FileUpload::make('logo')
                        ->label('Logo')
                        ->image()
                        ->directory('barbearia')
                        ->imagePreviewHeight('80')
                        ->nullable()
                        ->helperText('Exibida no chatbot de agendamento e no cabeçalho do sistema.'),
                ]),

            Section::make('Dias de Funcionamento')
                ->description('Selecione os dias da semana em que a barbearia atende. Apenas esses dias aparecerão no agendamento.')
                ->schema([
                    CheckboxList::make('dias_funcionamento')
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

            Section::make('Horários e Slots')
                ->description('Define como os slots de agendamento são gerados para todos os barbeiros.')
                ->columns(3)
                ->schema([
                    TextInput::make('horario_abertura')
                        ->label('Abertura')
                        ->placeholder('08:00')
                        ->helperText('Primeiro slot do dia (ex: 08:00)')
                        ->required(),

                    TextInput::make('horario_encerramento')
                        ->label('Encerramento')
                        ->placeholder('19:00')
                        ->helperText('Nenhum atendimento pode terminar após esse horário')
                        ->required(),

                    Select::make('intervalo_minutos')
                        ->label('Intervalo entre slots')
                        ->options([
                            30  => '30 minutos',
                            45  => '45 minutos',
                            60  => '1 hora (padrão)',
                            90  => '1h30min',
                            120 => '2 horas',
                        ])
                        ->default(60)
                        ->required(),
                ]),

            Section::make('Regras de Mensalistas')
                ->description('Limite global de cortes por semana para clientes mensalistas. Pode ser sobrescrito individualmente no cadastro do mensalista.')
                ->schema([
                    TextInput::make('mensalista_limite_cortes_semana')
                        ->label('Limite de cortes por semana (global)')
                        ->numeric()
                        ->default(1)
                        ->minValue(1)
                        ->maxValue(7)
                        ->required()
                        ->helperText('Controle semanal é mais preciso que mensal, pois o mês não tem semanas fixas.'),
                ]),

            Section::make('Financeiro')
                ->description('Define a divisão de receita entre a barbearia e os barbeiros.')
                ->columns(2)
                ->schema([
                    TextInput::make('percentual_barbearia')
                        ->label('Percentual da Barbearia (%)')
                        ->numeric()
                        ->default(60)
                        ->minValue(0)
                        ->maxValue(100)
                        ->suffix('%')
                        ->required()
                        ->live()
                        ->helperText('Percentual da receita retido pela barbearia sobre cada serviço.'),

                    Placeholder::make('percentual_barbeiros_info')
                        ->label('Percentual dos Barbeiros')
                        ->content(fn ($get) => (100 - (float) ($get('percentual_barbearia') ?? 60)) . '%')
                        ->helperText('Calculado automaticamente: 100% − percentual da barbearia.'),
                ]),

        ]);
    }
}
