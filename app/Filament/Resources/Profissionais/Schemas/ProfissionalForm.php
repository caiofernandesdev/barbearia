<?php

namespace App\Filament\Resources\Profissionais\Schemas;

use Carbon\Carbon;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProfissionalForm
{
    /** 0=Dom ... 6=Sáb, nomes no idioma do estabelecimento. */
    private static function dias(): array
    {
        return collect(range(0, 6))->mapWithKeys(fn ($d) => [
            $d => Str::ucfirst(Carbon::now()->startOfWeek(Carbon::SUNDAY)->addDays($d)->locale(app()->getLocale())->isoFormat('dddd')),
        ])->all();
    }

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
        return collect(self::dias())->map(
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
                ->label(__('painel.profissional.nome'))
                ->required()
                ->maxLength(100),

            // Opcional de propósito: a coluna é nullable e todo o envio já checa
            // telefone vazio antes de disparar. Exigir aqui travava a edição de
            // profissionais cadastrados antes deste campo existir.
            TextInput::make('telefone')
                ->label(__('painel.profissional.whatsapp'))
                ->tel()
                ->maxLength(20)
                ->placeholder(__('painel.profissional.whatsapp_ph'))
                ->helperText(__('painel.profissional.whatsapp_help')),

            TextInput::make('limite_mensalistas')
                ->label(__('painel.profissional.limite'))
                ->numeric()
                ->required()
                ->default(10)
                ->minValue(0),

            TextInput::make('comissao_percentual')
                ->label(__('painel.profissional.comissao'))
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->maxValue(100)
                ->suffix('%')
                ->helperText(__('painel.profissional.comissao_help')),

            FileUpload::make('foto')
                ->label(__('painel.profissional.foto'))
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
                ->label(__('painel.profissional.ativo'))
                ->default(true),

            Section::make(__('painel.profissional.sec_servicos'))
                ->description(__('painel.profissional.sec_servicos_desc'))
                ->schema([
                    CheckboxList::make('servicos')
                        ->label('')
                        ->relationship('servicos', 'nome')
                        ->columns(3),
                ]),

            Section::make(__('painel.profissional.sec_dias'))
                ->description(__('painel.profissional.sec_dias_desc'))
                ->schema([
                    CheckboxList::make('dias_trabalho')
                        ->label('')
                        ->options(self::dias())
                        ->columns(4)
                        ->default([1, 2, 3, 4, 5, 6])
                        // Reativo: os campos de horário por dia seguem esta seleção
                        ->live(),
                ]),

            Section::make(__('painel.profissional.sec_horarios'))
                ->description(__('painel.profissional.sec_horarios_desc'))
                ->schema([
                    Toggle::make('horarios_por_dia_ativo')
                        ->label(__('painel.profissional.por_dia_ativo'))
                        ->helperText(__('painel.profissional.por_dia_ativo_help'))
                        ->default(false)
                        ->live(),

                    // Label explícito: o Filament 4 ignora ->label('') e cai no
                    // nome da coluna ("Horarios trabalho")
                    CheckboxList::make('horarios_trabalho')
                        ->label(__('painel.profissional.horarios_todos'))
                        ->options(self::opcoesHorarios())
                        ->columns(6)
                        ->visible(fn ($get) => ! $get('horarios_por_dia_ativo')),

                    ...self::camposPorDia(),
                ]),
        ]);
    }
}
