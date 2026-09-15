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
                ->label(__('painel.agendamento.cliente_nome'))
                ->required()
                ->maxLength(100),

            TextInput::make('cliente_telefone')
                ->label(__('painel.agendamento.telefone'))
                // Sem ->tel(): a validação de formato do Filament rejeita número
                // colado/autopreenchido no iOS (caractere invisível). Campo livre;
                // guardamos só os dígitos (ou null quando vazio) ao salvar.
                ->extraInputAttributes(['inputmode' => 'tel'])
                ->dehydrateStateUsing(fn ($state) => preg_replace('/\D/', '', (string) $state) ?: null)
                ->maxLength(30),

            Select::make('profissional_id')
                ->label(__('painel.agendamento.profissional'))
                ->required()
                ->options(Profissional::where('ativo', true)->pluck('nome', 'id')),

            Select::make('servico_ids')
                ->label(__('painel.agendamento.servicos'))
                ->multiple()
                ->required()
                ->searchable()
                ->options(Servico::where('ativo', true)->orderBy('ordem')->get()
                    ->mapWithKeys(fn ($s) => [$s->id => $s->nome.' — R$ '.number_format((float) $s->preco, 2, ',', '.')])->all())
                ->helperText(__('painel.agendamento.servicos_help')),

            DateTimePicker::make('data_hora')
                ->label(__('painel.agendamento.data_hora'))
                ->required()
                ->seconds(false),

            Select::make('status')
                ->label(__('painel.agendamento.status'))
                ->required()
                ->options([
                    'pendente' => __('painel.agendamento.st_pendente'),
                    'confirmado' => __('painel.agendamento.st_confirmado'),
                    'concluido' => __('painel.agendamento.st_concluido'),
                    'cancelado' => __('painel.agendamento.st_cancelado'),
                ])
                ->default('pendente'),

            Toggle::make('mensalista')
                ->label(__('painel.agendamento.mensalista'))
                ->default(false),

            Textarea::make('observacao')
                ->label(__('painel.agendamento.observacao'))
                ->nullable()
                ->rows(3),
        ]);
    }
}
