<?php

namespace App\Filament\SuperAdmin\Resources\Planos;

use App\Filament\SuperAdmin\Resources\Planos\Pages\CreatePlano;
use App\Filament\SuperAdmin\Resources\Planos\Pages\EditPlano;
use App\Filament\SuperAdmin\Resources\Planos\Pages\ListPlanos;
use App\Models\Plano;
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

            CheckboxList::make('features')
                ->label('Módulos inclusos')
                ->options([
                    'mensalistas'        => 'Mensalistas',
                    'indisponibilidades' => 'Indisponibilidades de agenda',
                    'relatorios'         => 'Relatórios e dashboard',
                    'repescagem'         => 'Repescagem de clientes',
                    'salario_emocional'  => 'Salário emocional',
                    'whatsapp'           => 'Integração WhatsApp',
                    'campos_agendamento' => 'Campos personalizados no agendamento',
                    'import_export'      => 'Importação / Exportação',
                ])
                ->columns(2),

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

                TextColumn::make('features')
                    ->label('Features')
                    ->formatStateUsing(fn ($record) => implode(', ', $record->features ?? []))
                    ->wrap(),

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
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListPlanos::route('/'),
            'create' => CreatePlano::route('/create'),
            'edit'   => EditPlano::route('/{record}/edit'),
        ];
    }
}
