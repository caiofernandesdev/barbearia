<?php

namespace App\Filament\Resources\Profissionais;

use App\Filament\Resources\Profissionais\Pages\CreateProfissional;
use App\Filament\Resources\Profissionais\Pages\EditProfissional;
use App\Filament\Resources\Profissionais\Pages\ListProfissionais;
use App\Filament\Resources\Profissionais\Schemas\ProfissionalForm;
use App\Filament\Resources\Profissionais\Tables\ProfissionaisTable;
use App\Models\Profissional;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;

class ProfissionalResource extends Resource
{
    protected static ?string $model = Profissional::class;

    protected static \BackedEnum|string|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Profissionais';

    protected static ?string $modelLabel = 'Profissional';

    protected static ?string $pluralModelLabel = 'Profissionais';

    protected static ?string $recordTitleAttribute = 'nome';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return ProfissionalForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProfissionaisTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProfissionais::route('/'),
            'create' => CreateProfissional::route('/create'),
            'edit' => EditProfissional::route('/{record}/edit'),
        ];
    }
}
