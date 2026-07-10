<?php

namespace App\Filament\Resources\ConfiguracoesBarbearia\Schemas;

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
                        ->imageResizeMode('contain')
                        ->imageResizeTargetWidth('800')
                        ->imageResizeTargetHeight('800')
                        ->maxSize(10240)
                        ->directory('barbearia')
                        ->imagePreviewHeight('80')
                        ->nullable()
                        ->helperText('Exibida no chatbot de agendamento e no cabeçalho do sistema.'),

                    Select::make('tema_agendamento')
                        ->label('Tema da página de agendamento')
                        ->options([
                            'escuro' => '🌙 Escuro (preto)',
                            'claro' => '☀️ Claro (branco)',
                        ])
                        ->default('escuro')
                        ->required()
                        ->helperText('Aparência da página pública onde os clientes agendam.'),

                    Placeholder::make('link_agendamento')
                        ->label('Link de agendamento — envie aos seus clientes')
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
                                .'onclick="navigator.clipboard.writeText(\''.e($url).'\').then(() => { this.textContent = \'✓ Copiado!\'; setTimeout(() => this.textContent = \'Copiar link\', 2000); })">Copiar link</button>'
                                .'<a href="'.e($url).'" target="_blank" style="font-size:13px;text-decoration:underline;opacity:.8;">abrir em nova aba</a>'
                                .'</div>'
                            );
                        }),
                ]),

            Section::make('Horários e Slots')
                ->description('Define como os slots de agendamento são gerados para todos os barbeiros.')
                ->columns(3)
                ->schema([
                    TextInput::make('horario_abertura')
                        ->label('Abertura')
                        ->type('time')
                        ->required(),

                    TextInput::make('horario_encerramento')
                        ->label('Encerramento')
                        ->type('time')
                        ->required(),

                    Select::make('intervalo_minutos')
                        ->label('Intervalo entre slots')
                        ->options([
                            15 => '15 minutos',
                            20 => '20 minutos',
                            30 => '30 minutos',
                            45 => '45 minutos',
                            60 => '1 hora',
                            90 => '1h30min',
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

            Section::make('WhatsApp')
                ->description('Configurações de mensagens automáticas por WhatsApp.')
                ->schema([
                    Select::make('dias_antecedencia_lembrete')
                        ->label('Enviar lembrete com antecedência de')
                        ->options([
                            1 => '1 dia antes',
                            2 => '2 dias antes',
                            3 => '3 dias antes',
                        ])
                        ->default(1)
                        ->required()
                        ->helperText('O comando agendamentos:lembretes usa esse valor.'),

                    Toggle::make('cancelar_nao_confirmados')
                        ->label('Cancelar automaticamente não confirmados')
                        ->helperText('Cancela agendamentos pendentes que não foram confirmados até X horas antes do horário.')
                        ->live(),

                    Select::make('horas_antecedencia_cancelamento')
                        ->label('Cancelar com antecedência de')
                        ->options([
                            1 => '1 hora antes',
                            2 => '2 horas antes',
                            3 => '3 horas antes',
                            6 => '6 horas antes',
                            12 => '12 horas antes',
                            24 => '24 horas antes (dia anterior)',
                        ])
                        ->default(2)
                        ->visible(fn ($get) => $get('cancelar_nao_confirmados'))
                        ->helperText('O sistema cancela e avisa o cliente por WhatsApp.'),

                    Textarea::make('mensagem_repescagem')
                        ->label('Mensagem padrão de repescagem')
                        ->rows(4)
                        ->placeholder("Olá, {nome}! 👋\n\nFaz tempo que não te vemos por aqui na {barbearia}!\nQue tal agendar um horário?\n\nAcesse: {link}")
                        ->helperText('Variáveis: {nome}, {barbearia}, {link}. Deixe vazio para usar a mensagem padrão do sistema.'),
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
                        ->content(fn ($get) => (100 - (float) ($get('percentual_barbearia') ?? 60)).'%')
                        ->helperText('Calculado automaticamente: 100% − percentual da barbearia.'),
                ]),

        ]);
    }
}
