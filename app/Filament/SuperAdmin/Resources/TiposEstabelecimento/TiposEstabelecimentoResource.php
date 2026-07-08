<?php

namespace App\Filament\SuperAdmin\Resources\TiposEstabelecimento;

use App\Filament\SuperAdmin\Resources\TiposEstabelecimento\Pages\CreateTipoEstabelecimento;
use App\Filament\SuperAdmin\Resources\TiposEstabelecimento\Pages\EditTipoEstabelecimento;
use App\Filament\SuperAdmin\Resources\TiposEstabelecimento\Pages\ListTiposEstabelecimento;
use App\Models\TipoEstabelecimento;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TiposEstabelecimentoResource extends Resource
{
    protected static ?string $model = TipoEstabelecimento::class;

    protected static ?string $navigationLabel = 'Tipos de Estabelecimento';
    protected static ?string $modelLabel = 'Tipo';
    protected static ?string $pluralModelLabel = 'Tipos de Estabelecimento';
    protected static ?string $slug = 'tipos';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-tag';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Tenants';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nome')
                ->label('Nome')
                ->required()
                ->placeholder('Ex: Barbearia, Salão de Beleza, Clínica...')
                ->maxLength(100),

            TextInput::make('icone')
                ->label('Ícone (emoji)')
                ->placeholder('💈')
                ->maxLength(10),

            Toggle::make('ativo')
                ->label('Ativo')
                ->default(true),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('icone')->label(''),
                TextColumn::make('nome')->label('Nome')->searchable()->sortable(),
                TextColumn::make('tenants_count')->label('Estabelecimentos')->counts('tenants')->sortable(),
                IconColumn::make('ativo')->label('Ativo')->boolean(),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListTiposEstabelecimento::route('/'),
            'create' => CreateTipoEstabelecimento::route('/create'),
            'edit'   => EditTipoEstabelecimento::route('/{record}/edit'),
        ];
    }
}
