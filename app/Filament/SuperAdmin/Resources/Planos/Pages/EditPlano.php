<?php

namespace App\Filament\SuperAdmin\Resources\Planos\Pages;

use App\Filament\SuperAdmin\Resources\Planos\PlanosResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPlano extends EditRecord
{
    protected static string $resource = PlanosResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /** Separa os slugs rel_* do array features para preencher o CheckboxList próprio. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $features = $data['features'] ?? [];
        $data['relatorios_inclusos'] = array_values(array_filter($features, fn ($f) => str_starts_with($f, 'rel_')));
        $data['features'] = array_values(array_filter($features, fn ($f) => ! str_starts_with($f, 'rel_')));

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return PlanosResource::mesclarRelatoriosNasFeatures($data);
    }
}
