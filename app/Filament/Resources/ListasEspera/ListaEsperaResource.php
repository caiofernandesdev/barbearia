<?php

namespace App\Filament\Resources\ListasEspera;

use App\Filament\Resources\ListasEspera\Pages\ListListasEspera;
use App\Models\ListaEspera;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ListaEsperaResource extends Resource
{
    protected static ?string $model = ListaEspera::class;

    protected static \BackedEnum|string|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Lista de Espera';

    protected static ?string $modelLabel = 'Lista de espera';

    protected static ?string $pluralModelLabel = 'Lista de Espera';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): string
    {
        return 'Agenda';
    }

    public static function canAccess(): bool
    {
        if (! auth()->user()?->isAdmin()) {
            return false;
        }
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;

        return $tenant?->hasFeature('lista_espera') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = ListaEspera::where('status', 'aguardando')->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function table(Table $table): Table
    {
        return ListasEsperaTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListListasEspera::route('/'),
        ];
    }
}
