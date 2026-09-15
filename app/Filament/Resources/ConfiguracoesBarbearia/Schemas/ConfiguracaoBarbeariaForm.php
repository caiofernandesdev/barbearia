<?php

namespace App\Filament\Resources\ConfiguracoesBarbearia\Schemas;

use App\Support\Locale;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ConfiguracaoBarbeariaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make(__('painel.config.sec_identidade'))
                ->columns(2)
                ->schema([
                    TextInput::make('nome_barbearia')
                        ->label(__('painel.config.nome_label'))
                        ->required()
                        ->maxLength(100)
                        ->helperText(__('painel.config.nome_help')),

                    FileUpload::make('logo')
                        ->label(__('painel.config.logo_label'))
                        ->image()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->imageResizeMode('contain')
                        ->imageResizeTargetWidth('800')
                        ->imageResizeTargetHeight('800')
                        ->maxSize(10240)
                        ->directory('barbearia')
                        ->imagePreviewHeight('80')
                        ->nullable()
                        ->helperText(__('painel.config.logo_help')),

                    Select::make('idioma')
                        ->label(__('painel.config.idioma_label'))
                        ->options(Locale::opcoes())
                        ->default(Locale::PADRAO)
                        ->required()
                        ->helperText(__('painel.config.idioma_help')),

                    Select::make('tema_agendamento')
                        ->label(__('painel.config.tema_label'))
                        ->options([
                            'escuro' => __('painel.config.tema_escuro'),
                            'claro' => __('painel.config.tema_claro'),
                            'tecnologico' => __('painel.config.tema_tecnologico'),
                            'feminino' => __('painel.config.tema_feminino'),
                            'neutro' => __('painel.config.tema_neutro'),
                        ])
                        ->default('escuro')
                        ->required()
                        ->helperText(__('painel.config.tema_help')),

                    Placeholder::make('link_agendamento')
                        ->label(__('painel.config.link_label'))
                        ->columnSpanFull()
                        ->content(function () {
                            $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;
                            if (! $tenant) {
                                return '—';
                            }
                            $url = url('/'.$tenant->slug);

                            // Estilos inline de propósito: imunes ao CSS compilado do Filament
                            return new HtmlString(
                                '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">'
                                .'<code style="padding:8px 12px;border-radius:8px;background:rgba(120,120,120,.15);font-size:13px;user-select:all;">'.e($url).'</code>'
                                .'<button type="button" style="padding:8px 14px;border-radius:8px;background:#f59e0b;color:#111827;font-weight:600;font-size:13px;cursor:pointer;" '
                                .'onclick="navigator.clipboard.writeText(\''.e($url).'\').then(() => { this.textContent = \''.e(__('painel.config.link_copiado')).'\'; setTimeout(() => this.textContent = \''.e(__('painel.config.link_copiar')).'\', 2000); })">'.e(__('painel.config.link_copiar')).'</button>'
                                .'<a href="'.e($url).'" target="_blank" style="font-size:13px;text-decoration:underline;opacity:.8;">'.e(__('painel.config.link_abrir')).'</a>'
                                .'</div>'
                            );
                        }),
                ]),

            Section::make(__('painel.config.sec_horarios'))
                ->description(__('painel.config.sec_horarios_desc'))
                ->columns(3)
                ->schema([
                    TextInput::make('horario_abertura')
                        ->label(__('painel.config.abertura'))
                        ->type('time')
                        ->required(),

                    TextInput::make('horario_encerramento')
                        ->label(__('painel.config.encerramento'))
                        ->type('time')
                        ->required(),

                    Select::make('intervalo_minutos')
                        ->label(__('painel.config.intervalo_label'))
                        ->options([
                            10 => __('painel.config.int_10'),
                            15 => __('painel.config.int_15'),
                            20 => __('painel.config.int_20'),
                            30 => __('painel.config.int_30'),
                            45 => __('painel.config.int_45'),
                            60 => __('painel.config.int_60'),
                            90 => __('painel.config.int_90'),
                            120 => __('painel.config.int_120'),
                        ])
                        ->default(60)
                        ->required(),

                    TextInput::make('dias_antecedencia_agendamento')
                        ->label(__('painel.config.dias_label'))
                        ->numeric()
                        ->default(14)
                        ->minValue(1)
                        ->maxValue(90)
                        ->required()
                        ->suffix(__('painel.config.dias_suffix'))
                        ->helperText(__('painel.config.dias_help')),
                ]),

            Section::make(__('painel.config.sec_mensalistas'))
                ->description(__('painel.config.sec_mensalistas_desc'))
                ->schema([
                    TextInput::make('mensalista_limite_cortes_semana')
                        ->label(__('painel.config.limite_label'))
                        ->numeric()
                        ->default(1)
                        ->minValue(1)
                        ->maxValue(7)
                        ->required()
                        ->helperText(__('painel.config.limite_help')),
                ]),

            Section::make(__('painel.config.sec_whatsapp'))
                ->description(__('painel.config.sec_whatsapp_desc'))
                ->schema([
                    Select::make('dias_antecedencia_lembrete')
                        ->label(__('painel.config.lembrete_label'))
                        ->options([
                            1 => __('painel.config.lembrete_1'),
                            2 => __('painel.config.lembrete_2'),
                            3 => __('painel.config.lembrete_3'),
                        ])
                        ->default(1)
                        ->required()
                        ->helperText(__('painel.config.lembrete_help')),

                    Toggle::make('cancelar_nao_confirmados')
                        ->label(__('painel.config.cancelar_label'))
                        ->helperText(__('painel.config.cancelar_help'))
                        ->live(),

                    Select::make('horas_antecedencia_cancelamento')
                        ->label(__('painel.config.horas_label'))
                        ->options([
                            1 => __('painel.config.horas_1'),
                            2 => __('painel.config.horas_2'),
                            3 => __('painel.config.horas_3'),
                            6 => __('painel.config.horas_6'),
                            12 => __('painel.config.horas_12'),
                            24 => __('painel.config.horas_24'),
                        ])
                        ->default(2)
                        ->visible(fn ($get) => $get('cancelar_nao_confirmados'))
                        ->helperText(__('painel.config.horas_help')),

                    Textarea::make('mensagem_repescagem')
                        ->label(__('painel.config.repescagem_label'))
                        ->rows(4)
                        ->placeholder(__('painel.config.repescagem_placeholder'))
                        ->helperText(__('painel.config.repescagem_help')),
                ]),

            Section::make(__('painel.config.sec_financeiro'))
                ->description(__('painel.config.sec_financeiro_desc'))
                ->columns(2)
                ->schema([
                    TextInput::make('percentual_barbearia')
                        ->label(__('painel.config.percentual_label'))
                        ->numeric()
                        ->default(60)
                        ->minValue(0)
                        ->maxValue(100)
                        ->suffix('%')
                        ->required()
                        ->live()
                        ->helperText(__('painel.config.percentual_help')),

                    Placeholder::make('percentual_barbeiros_info')
                        ->label(__('painel.config.percentual_prof_label'))
                        ->content(fn ($get) => (100 - (float) ($get('percentual_barbearia') ?? 60)).'%')
                        ->helperText(__('painel.config.percentual_prof_help')),
                ]),

        ]);
    }
}
