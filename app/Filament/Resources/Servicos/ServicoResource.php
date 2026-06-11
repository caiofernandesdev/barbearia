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
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;

class ServicoResource extends Resource
{
    protected static ?string $model = Servico::class;

    protected static \BackedEnum|string|null $navigationIcon = Heroicon::OutlinedScissors;

    protected static ?string $navigationLabel = 'Serviços';

    protected static ?string $modelLabel = 'Serviço';

    protected static ?string $pluralModelLabel = 'Serviços';

    protected static ?string $recordTitleAttribute = 'nome';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
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
