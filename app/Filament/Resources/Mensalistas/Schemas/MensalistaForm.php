<?php

namespace App\Filament\Resources\Mensalistas\Schemas;

use Carbon\Carbon;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class MensalistaForm
{
    /** 0=Dom ... 6=Sáb, nomes no idioma do estabelecimento. */
    private static function dias(): array
    {
        return collect(range(0, 6))->mapWithKeys(fn ($d) => [
            $d => Str::ucfirst(Carbon::now()->startOfWeek(Carbon::SUNDAY)->addDays($d)->locale(app()->getLocale())->isoFormat('dddd')),
        ])->all();
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            TextInput::make('nome')
                ->label(__('painel.cliente.nome'))
                ->required()
                ->maxLength(100),

            TextInput::make('telefone')
                ->label(__('painel.cliente.telefone'))
                ->required()
                ->tel()
                ->maxLength(20)
                ->helperText(__('painel.cliente.telefone_help')),

            Select::make('tipo')
                ->label(__('painel.cliente.tipo'))
                ->options([
                    'avulso' => __('painel.cliente.tipo_avulso'),
                    'mensalista' => __('painel.cliente.tipo_mensalista'),
                    'mensalista_fixo' => __('painel.cliente.tipo_fixo'),
                ])
                ->default('avulso')
                ->required()
                ->live()
                ->helperText(__('painel.cliente.tipo_help')),

            TextInput::make('limite_cortes_semana')
                ->label(__('painel.cliente.limite'))
                ->numeric()
                ->default(1)
                ->minValue(1)
                ->maxValue(7)
                ->helperText(__('painel.cliente.limite_help'))
                ->visible(fn (Get $get): bool => $get('tipo') === 'mensalista'),

            TextInput::make('valor_mensalidade')
                ->label(__('painel.cliente.valor'))
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->prefix('R$')
                ->helperText(__('painel.cliente.valor_help'))
                ->visible(fn (Get $get): bool => in_array($get('tipo'), ['mensalista', 'mensalista_fixo'])),

            // Repeater para horários fixos — visível apenas quando tipo = mensalista_fixo
            Repeater::make('horariosFixos')
                ->label(__('painel.cliente.rep_titulo'))
                ->relationship()
                ->schema([
                    Select::make('profissional_id')
                        ->label(__('painel.agendamento.profissional'))
                        ->relationship('profissional', 'nome')
                        ->required(),

                    Select::make('servico_id')
                        ->label(__('painel.agendamento.col_servico'))
                        ->relationship('servico', 'nome')
                        ->required(),

                    Select::make('dia_semana')
                        ->label(__('painel.cliente.rep_dia'))
                        ->options(self::dias())
                        ->required(),

                    Select::make('hora')
                        ->label(__('painel.cliente.rep_hora'))
                        ->options(
                            collect(range(6, 22))->flatMap(fn ($h) => [
                                sprintf('%02d:00', $h) => sprintf('%02d:00', $h),
                                sprintf('%02d:30', $h) => sprintf('%02d:30', $h),
                            ])->prepend(__('painel.cliente.rep_selecione'), '')->toArray()
                        )
                        ->required(),

                    Toggle::make('ativo')
                        ->label(__('painel.cliente.rep_ativo'))
                        ->default(true),
                ])
                ->columns(2)
                ->addActionLabel(__('painel.cliente.rep_add'))
                ->helperText(__('painel.cliente.rep_help'))
                ->visible(fn (Get $get): bool => $get('tipo') === 'mensalista_fixo'),

        ]);
    }
}
