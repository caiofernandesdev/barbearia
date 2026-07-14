<?php

namespace App\Filament\Resources\Profissionais\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProfissionalForm
{
    /** 0=Dom ... 6=Sáb — mesma convenção do Carbon::dayOfWeek */
    private const DIAS = [
        0 => 'Domingo',
        1 => 'Segunda-feira',
        2 => 'Terça-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sábado',
    ];

    /** Slots de meia em meia hora, das 6h às 23h30 */
    private static function opcoesHorarios(): array
    {
        return collect(range(6, 23))->flatMap(fn ($h) => [
            sprintf('%02d:00', $h) => sprintf('%02d:00', $h),
            sprintf('%02d:30', $h) => sprintf('%02d:30', $h),
        ])->toArray();
    }

    /** Uma lista de horários por dia da semana, visível só nos dias trabalhados */
    private static function camposPorDia(): array
    {
        return collect(self::DIAS)->map(
            fn (string $label, int $num) => CheckboxList::make("horarios_por_dia.{$num}")
                ->label($label)
                ->options(self::opcoesHorarios())
                ->columns(6)
                ->visible(function ($get) use ($num) {
                    if (! $get('horarios_por_dia_ativo')) {
                        return false;
                    }
                    // O CheckboxList devolve os dias como string; compara normalizado
                    $dias = array_map('strval', $get('dias_trabalho') ?? []);

                    return in_array((string) $num, $dias, true);
                })
        )->values()->all();
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nome')
                ->label('Nome')
                ->required()
                ->maxLength(100),

            // Opcional de propósito: a coluna é nullable e todo o envio já checa
            // telefone vazio antes de disparar. Exigir aqui travava a edição de
            // profissionais cadastrados antes deste campo existir.
            TextInput::make('telefone')
                ->label('WhatsApp do profissional')
                ->tel()
                ->maxLength(20)
                ->placeholder('(11) 99999-9999')
                ->helperText('Recebe notificações de novos agendamentos e cancelamentos. Sem telefone, ele não recebe nenhum aviso.'),

            TextInput::make('limite_mensalistas')
                ->label('Limite de Mensalistas')
                ->numeric()
                ->required()
                ->default(10)
                ->minValue(0),

            TextInput::make('comissao_percentual')
                ->label('Comissão (%)')
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->maxValue(100)
                ->suffix('%')
                ->helperText('Percentual sobre a receita de serviços. Usado na Consolidação Financeira do Salário Emocional.'),

            FileUpload::make('foto')
                ->label('Foto')
                ->image()
                // Sem HEIC: navegadores de PC não exibem; o iPhone converte p/ JPEG
                // automaticamente quando o campo não aceita o formato
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                // Redimensiona no navegador ANTES do upload — fotos de galeria de
                // celular (5-15MB) estourariam os limites de upload do servidor
                ->imageResizeMode('contain')
                ->imageResizeTargetWidth('1200')
                ->imageResizeTargetHeight('1200')
                ->maxSize(10240)
                ->directory('profissionais')
                ->nullable(),

            Toggle::make('ativo')
                ->label('Ativo')
                ->default(true),

            Section::make('Serviços que realiza')
                ->description('Marque os serviços que este profissional atende. Se nenhum for marcado, ele atende todos os serviços.')
                ->schema([
                    CheckboxList::make('servicos')
                        ->label('')
                        ->relationship('servicos', 'nome')
                        ->columns(3),
                ]),

            Section::make('Dias de Trabalho')
                ->description('Selecione os dias da semana em que este profissional atende.')
                ->schema([
                    CheckboxList::make('dias_trabalho')
                        ->label('')
                        ->options(self::DIAS)
                        ->columns(4)
                        ->default([1, 2, 3, 4, 5, 6])
                        // Reativo: os campos de horário por dia seguem esta seleção
                        ->live(),
                ]),

            Section::make('Horários Específicos')
                ->description('Opcional. Se deixar tudo em branco, o sistema usa todos os slots do intervalo configurado.')
                ->schema([
                    Toggle::make('horarios_por_dia_ativo')
                        ->label('Definir horários diferentes para cada dia')
                        ->helperText('Desligado: a mesma lista de horários vale para todos os dias. Ligado: você escolhe os horários dia a dia.')
                        ->default(false)
                        ->live(),

                    // Label explícito: o Filament 4 ignora ->label('') e cai no
                    // nome da coluna ("Horarios trabalho")
                    CheckboxList::make('horarios_trabalho')
                        ->label('Horários (todos os dias)')
                        ->options(self::opcoesHorarios())
                        ->columns(6)
                        ->visible(fn ($get) => ! $get('horarios_por_dia_ativo')),

                    ...self::camposPorDia(),
                ]),
        ]);
    }
}
