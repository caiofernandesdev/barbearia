<?php

namespace App\Filament\Resources\Indisponibilidades;

use App\Filament\Resources\Indisponibilidades\Pages\CreateIndisponibilidade;
use App\Filament\Resources\Indisponibilidades\Pages\EditIndisponibilidade;
use App\Filament\Resources\Indisponibilidades\Pages\ListIndisponibilidades;
use App\Filament\Resources\Indisponibilidades\Schemas\IndisponibilidadeForm;
use App\Filament\Resources\Indisponibilidades\Tables\IndisponibilidadesTable;
use App\Models\Indisponibilidade;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;

class IndisponibilidadeResource extends Resource
{
    protected static ?string $model = Indisponibilidade::class;

    protected static \BackedEnum|string|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static ?string $navigationLabel = 'Indisponibilidades';

    protected static ?string $modelLabel = 'Indisponibilidade';

    protected static ?string $pluralModelLabel = 'Indisponibilidades';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): string
    {
        return 'Agenda';
    }

    public static function canAccess(): bool
    {
        if (! auth()->user()?->isAdmin()) return false;
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;
        return $tenant?->hasFeature('indisponibilidades') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return IndisponibilidadeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IndisponibilidadesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListIndisponibilidades::route('/'),
            'create' => CreateIndisponibilidade::route('/create'),
            'edit'   => EditIndisponibilidade::route('/{record}/edit'),
        ];
    }
}
