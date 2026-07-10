<?php

namespace App\Filament\Support;

use App\Models\CampoPersonalizado;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Peças reutilizáveis para qualquer tabela de agendamentos:
 * coluna "Detalhes" (respostas dos campos personalizados) e filtros
 * dinâmicos por campo — mesmas em Agendamentos, Relatórios e painéis.
 */
class AgendamentoTabela
{
    public static function colunaDetalhes(bool $ocultaPorPadrao = true): TextColumn
    {
        return TextColumn::make('dados_extras')
            ->label('Detalhes')
            ->formatStateUsing(function ($record) {
                $extras = $record->dados_extras;
                if (empty($extras)) {
                    return '—';
                }

                return collect($extras)->map(fn ($v, $k) => ucfirst(str_replace('_', ' ', $k)).': '.$v)->implode(' · ');
            })
            ->wrap()
            ->toggleable(isToggledHiddenByDefault: $ocultaPorPadrao);
    }

    /**
     * Um filtro por campo personalizado ativo do tenant.
     *
     * As respostas ficam em agendamentos.dados_extras (JSON keyed pelo slug):
     * select/toggle viram SelectFilter; texto vira busca parcial (LIKE).
     */
    public static function filtrosCamposExtras(): array
    {
        return CampoPersonalizado::where('ativo', true)
            ->orderBy('ordem')
            ->get()
            ->map(function (CampoPersonalizado $campo) {
                $jsonPath = 'dados_extras->'.$campo->slug;

                if ($campo->tipo === 'select' || $campo->tipo === 'toggle') {
                    $opcoes = $campo->tipo === 'toggle'
                        ? ['Sim' => 'Sim', 'Não' => 'Não']
                        : collect($campo->opcoes ?? [])->mapWithKeys(fn ($o) => [$o => $o])->all();

                    return SelectFilter::make('extra_'.$campo->slug)
                        ->label($campo->nome)
                        ->options($opcoes)
                        ->attribute($jsonPath);
                }

                return Filter::make('extra_'.$campo->slug)
                    ->label($campo->nome)
                    ->form([
                        TextInput::make('valor')->label($campo->nome),
                    ])
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['valor'] ?? null,
                        fn ($q, $v) => $q->where($jsonPath, 'like', "%{$v}%")
                    ))
                    ->indicateUsing(fn (array $data) => ($data['valor'] ?? null)
                        ? [$campo->nome.': '.$data['valor']]
                        : []);
            })
            ->all();
    }
}
