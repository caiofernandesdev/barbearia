<?php

namespace App\Filament\Resources\CamposPersonalizados;

use App\Filament\Resources\CamposPersonalizados\Pages\CreateCampoPersonalizado;
use App\Filament\Resources\CamposPersonalizados\Pages\EditCampoPersonalizado;
use App\Filament\Resources\CamposPersonalizados\Pages\ListCamposPersonalizados;
use App\Models\CampoPersonalizado;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CampoPersonalizadoResource extends Resource
{
    protected static ?string $model = CampoPersonalizado::class;

    protected static ?string $slug = 'campos-agendamento';

    public static function getNavigationLabel(): string
    {
        return __('painel.nav.campos');
    }

    public static function getModelLabel(): string
    {
        return __('painel.model.campo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('painel.model.campos');
    }

    protected static ?int $navigationSort = 3;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-adjustments-horizontal';
    }

    public static function getNavigationGroup(): string
    {
        return __('painel.group.cadastros');
    }

    public static function canAccess(): bool
    {
        if (! auth()->user()?->temPermissao('campos_agendamento')) {
            return false;
        }
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;

        return $tenant?->hasFeature('campos_agendamento') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nome')
                ->label(__('painel.campo.nome'))
                ->required()
                ->maxLength(100)
                ->placeholder(__('painel.campo.nome_ph'))
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($set, $state) => $set('slug', Str::slug($state))),

            TextInput::make('slug')
                ->label(__('painel.campo.slug'))
                ->required()
                ->alphaDash()
                ->maxLength(60)
                ->helperText(__('painel.campo.slug_help')),

            Select::make('tipo')
                ->label(__('painel.campo.tipo'))
                ->options([
                    'select' => __('painel.campo.tipo_select'),
                    'text' => __('painel.campo.tipo_text'),
                    'toggle' => __('painel.campo.tipo_toggle'),
                ])
                ->default('select')
                ->required()
                ->live(),

            TagsInput::make('opcoes')
                ->label(__('painel.campo.opcoes'))
                ->placeholder(__('painel.campo.opcoes_ph'))
                ->helperText(__('painel.campo.opcoes_help'))
                ->visible(fn ($get) => $get('tipo') === 'select'),

            Toggle::make('obrigatorio')
                ->label(__('painel.campo.obrigatorio'))
                ->helperText(__('painel.campo.obrigatorio_help')),

            TextInput::make('ordem')
                ->label(__('painel.campo.ordem'))
                ->numeric()
                ->default(0)
                ->minValue(0),

            Toggle::make('ativo')
                ->label(__('painel.campo.ativo'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->label(__('painel.campo.col_campo'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('tipo')
                    ->label(__('painel.campo.tipo'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'select' => __('painel.campo.badge_lista'),
                        'text' => __('painel.campo.badge_texto'),
                        'toggle' => __('painel.campo.badge_simnao'),
                        default => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        'select' => 'info',
                        'text' => 'warning',
                        'toggle' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('opcoes')
                    ->label(__('painel.campo.col_opcoes'))
                    ->formatStateUsing(fn ($record) => implode(', ', $record->opcoes ?? []))
                    ->wrap()
                    ->limit(50),

                IconColumn::make('obrigatorio')
                    ->label(__('painel.campo.obrigatorio'))
                    ->boolean(),

                TextColumn::make('ordem')
                    ->label(__('painel.campo.col_ordem'))
                    ->sortable(),

                IconColumn::make('ativo')
                    ->label(__('painel.campo.ativo'))
                    ->boolean(),
            ])
            ->defaultSort('ordem')
            ->reorderable('ordem')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCamposPersonalizados::route('/'),
            'create' => CreateCampoPersonalizado::route('/create'),
            'edit' => EditCampoPersonalizado::route('/{record}/edit'),
        ];
    }
}
