<?php

namespace App\Filament\Resources\CamposPersonalizados;

use App\Filament\Resources\CamposPersonalizados\Pages\CreateCampoPersonalizado;
use App\Filament\Resources\CamposPersonalizados\Pages\EditCampoPersonalizado;
use App\Filament\Resources\CamposPersonalizados\Pages\ListCamposPersonalizados;
use App\Models\CampoPersonalizado;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CampoPersonalizadoResource extends Resource
{
    protected static ?string $model = CampoPersonalizado::class;
    protected static ?string $slug = 'campos-agendamento';

    protected static ?string $navigationLabel = 'Campos do Agendamento';
    protected static ?string $modelLabel = 'Campo';
    protected static ?string $pluralModelLabel = 'Campos do Agendamento';
    protected static ?int $navigationSort = 3;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-adjustments-horizontal';
    }

    public static function getNavigationGroup(): string
    {
        return 'Cadastros';
    }

    public static function canAccess(): bool
    {
        if (! auth()->user()?->isAdmin()) return false;
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;
        return $tenant?->hasFeature('campos_agendamento') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nome')
                ->label('Nome do campo')
                ->required()
                ->maxLength(100)
                ->placeholder('Ex: Convênio, Porte do animal...')
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($set, $state) => $set('slug', \Illuminate\Support\Str::slug($state))),

            TextInput::make('slug')
                ->label('Identificador')
                ->required()
                ->alphaDash()
                ->maxLength(60)
                ->helperText('Gerado automaticamente. Usado internamente.'),

            Select::make('tipo')
                ->label('Tipo de campo')
                ->options([
                    'select' => 'Lista de opções (dropdown)',
                    'text'   => 'Texto livre',
                    'toggle' => 'Sim / Não',
                ])
                ->default('select')
                ->required()
                ->live(),

            TagsInput::make('opcoes')
                ->label('Opções')
                ->placeholder('Adicionar opção...')
                ->helperText('As opções que o cliente poderá escolher')
                ->visible(fn ($get) => $get('tipo') === 'select'),

            Toggle::make('obrigatorio')
                ->label('Obrigatório')
                ->helperText('Cliente não pode pular este campo'),

            TextInput::make('ordem')
                ->label('Ordem de exibição')
                ->numeric()
                ->default(0)
                ->minValue(0),

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
                    ->label('Campo')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'select' => 'Lista',
                        'text'   => 'Texto',
                        'toggle' => 'Sim/Não',
                        default  => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        'select' => 'info',
                        'text'   => 'warning',
                        'toggle' => 'success',
                        default  => 'gray',
                    }),

                TextColumn::make('opcoes')
                    ->label('Opções')
                    ->formatStateUsing(fn ($record) => implode(', ', $record->opcoes ?? []))
                    ->wrap()
                    ->limit(50),

                IconColumn::make('obrigatorio')
                    ->label('Obrigatório')
                    ->boolean(),

                TextColumn::make('ordem')
                    ->label('Ordem')
                    ->sortable(),

                IconColumn::make('ativo')
                    ->label('Ativo')
                    ->boolean(),
            ])
            ->defaultSort('ordem')
            ->reorderable('ordem')
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListCamposPersonalizados::route('/'),
            'create' => CreateCampoPersonalizado::route('/create'),
            'edit'   => EditCampoPersonalizado::route('/{record}/edit'),
        ];
    }
}
