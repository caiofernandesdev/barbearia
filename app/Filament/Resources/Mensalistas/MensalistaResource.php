<?php

namespace App\Filament\Resources\Mensalistas;

use App\Filament\Resources\Mensalistas\Pages\CreateMensalista;
use App\Filament\Resources\Mensalistas\Pages\EditMensalista;
use App\Filament\Resources\Mensalistas\Pages\ListMensalistas;
use App\Filament\Resources\Mensalistas\Schemas\MensalistaForm;
use App\Filament\Resources\Mensalistas\Tables\MensalistasTable;
use App\Models\Mensalista;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class MensalistaResource extends Resource
{
    protected static ?string $model = Mensalista::class;

    protected static \BackedEnum|string|null $navigationIcon  = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Mensalistas';
    protected static ?string $modelLabel      = 'Mensalista';
    protected static ?string $pluralModelLabel = 'Mensalistas';
    protected static ?int    $navigationSort  = 4;
    protected static ?string $recordTitleAttribute = 'nome';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return MensalistaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MensalistasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListMensalistas::route('/'),
            'create' => CreateMensalista::route('/create'),
            'edit'   => EditMensalista::route('/{record}/edit'),
        ];
    }
}
