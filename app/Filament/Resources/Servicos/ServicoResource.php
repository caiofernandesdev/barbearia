<?php

namespace App\Filament\Resources\Servicos;

use App\Filament\Resources\Servicos\Pages\CreateServico;
use App\Filament\Resources\Servicos\Pages\EditServico;
use App\Filament\Resources\Servicos\Pages\ListServicos;
use App\Filament\Resources\Servicos\Schemas\ServicoForm;
use App\Filament\Resources\Servicos\Tables\ServicosTable;
use App\Models\Servico;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ServicoResource extends Resource
{
    protected static ?string $model = Servico::class;

    protected static \BackedEnum|string|null $navigationIcon = Heroicon::OutlinedScissors;

    public static function getNavigationLabel(): string
    {
        return __('painel.nav.servicos');
    }

    public static function getModelLabel(): string
    {
        return __('painel.model.servico');
    }

    public static function getPluralModelLabel(): string
    {
        return __('painel.model.servicos');
    }

    protected static ?string $recordTitleAttribute = 'nome';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): string
    {
        return __('painel.group.cadastros');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->temPermissao('servicos') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return ServicoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServicosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServicos::route('/'),
            'create' => CreateServico::route('/create'),
            'edit' => EditServico::route('/{record}/edit'),
        ];
    }
}
