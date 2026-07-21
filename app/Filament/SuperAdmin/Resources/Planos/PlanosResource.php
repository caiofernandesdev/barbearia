<?php

namespace App\Filament\SuperAdmin\Resources\Planos;

use App\Filament\SuperAdmin\Resources\Planos\Pages\CreatePlano;
use App\Filament\SuperAdmin\Resources\Planos\Pages\EditPlano;
use App\Filament\SuperAdmin\Resources\Planos\Pages\ListPlanos;
use App\Models\Plano;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlanosResource extends Resource
{
    protected static ?string $model = Plano::class;

    protected static ?string $navigationLabel = 'Planos';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-credit-card';
    }

    protected static ?string $modelLabel = 'Plano';

    protected static ?string $pluralModelLabel = 'Planos';

    public static function getNavigationGroup(): ?string
    {
        return 'Planos';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nome')
                ->label('Nome')
                ->required()
                ->maxLength(100),

            Textarea::make('descricao')
                ->label('Descrição')
                ->rows(2),

            TextInput::make('preco_mensal')
                ->label('Preço Mensal (R$)')
                ->numeric()
                ->prefix('R$')
                ->default(0),

            TextInput::make('max_profissionais')
                ->label('Máximo de profissionais')
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->helperText('0 = ilimitado.'),

            TextInput::make('max_usuarios')
                ->label('Máximo de usuários (logins)')
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->helperText('0 = ilimitado. Conta admins e profissionais com acesso.'),

            CheckboxList::make('features')
                ->label('Módulos inclusos')
                ->options([
                    'mensalistas' => 'Mensalistas',
                    'indisponibilidades' => 'Indisponibilidades de agenda',
                    'relatorios' => 'Relatórios e dashboard',
                    'repescagem' => 'Repescagem de clientes',
                    'salario_emocional' => 'Salário emocional',
                    'whatsapp' => 'Integração WhatsApp',
                    'campos_agendamento' => 'Campos personalizados no agendamento',
                    'agenda_fixa' => 'Agenda Fixa (planejamento mensal do cliente)',
                    'lista_espera' => 'Lista de espera (dias lotados)',
                    'import_export' => 'Importação / Exportação',
                ])
                ->columns(2)
                ->live(),

            // Sub-módulos: quais relatórios o plano inclui. Salvo junto no array
            // features (slugs rel_*) — merge feito nas páginas Create/Edit.
            // Nenhum marcado = todos liberados (retrocompat com planos antigos).
            CheckboxList::make('relatorios_inclusos')
                ->label('Relatórios inclusos no plano')
                ->helperText('Nenhum marcado = todos os relatórios liberados. O Salário Emocional é o módulo próprio acima.')
                ->options(Plano::RELATORIOS)
                ->columns(2)
                ->visible(fn ($get) => in_array('relatorios', $get('features') ?? [])),

            Toggle::make('ativo')
                ->label('Ativo')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('preco_mensal')
                    ->label('Preço/mês')
                    ->money('BRL')
                    ->sortable(),

                // A lista de features crua poluía demais; o detalhe fica na edição
                TextColumn::make('features')
                    ->label('Módulos')
                    ->badge()
                    ->getStateUsing(fn ($record) => collect($record->features ?? [])
                        ->reject(fn ($f) => str_starts_with($f, 'rel_'))
                        ->count().' módulos')
                    ->color('gray'),

                TextColumn::make('limites')
                    ->label('Limites')
                    ->getStateUsing(fn ($record) => sprintf(
                        '%s prof · %s logins',
                        $record->max_profissionais > 0 ? $record->max_profissionais : '∞',
                        $record->max_usuarios > 0 ? $record->max_usuarios : '∞',
                    )),

                TextColumn::make('tenants_count')
                    ->label('Clientes')
                    ->counts('tenants')
                    ->sortable(),

                IconColumn::make('ativo')
                    ->label('Ativo')
                    ->boolean(),
            ])
            ->defaultSort('preco_mensal')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlanos::route('/'),
            'create' => CreatePlano::route('/create'),
            'edit' => EditPlano::route('/{record}/edit'),
        ];
    }

    /**
     * Junta os relatórios granulares (rel_*) de volta no array features.
     * Sem o módulo 'relatorios' marcado, os rel_* são descartados.
     */
    public static function mesclarRelatoriosNasFeatures(array $data): array
    {
        $features = array_values(array_filter($data['features'] ?? [], fn ($f) => ! str_starts_with($f, 'rel_')));
        $relatorios = $data['relatorios_inclusos'] ?? [];

        if (in_array('relatorios', $features, true)) {
            $features = array_values(array_unique(array_merge($features, $relatorios)));
        }

        $data['features'] = $features;
        unset($data['relatorios_inclusos']);

        return $data;
    }
}
