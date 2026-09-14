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

    public static function getNavigationLabel(): string
    {
        return __('painel.nav.lista_espera');
    }

    public static function getModelLabel(): string
    {
        return __('painel.model.lista_espera');
    }

    public static function getPluralModelLabel(): string
    {
        return __('painel.model.lista_espera');
    }

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): string
    {
        return __('painel.group.agenda');
    }

    public static function canAccess(): bool
    {
        if (! auth()->user()?->temPermissao('lista_espera')) {
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
