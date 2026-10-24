<?php

namespace App\Filament\SuperAdmin\Resources\Tenants\Pages;

use App\Filament\SuperAdmin\Resources\Tenants\TenantsResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantsResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /** Separa os slugs rel_* do features_custom pra preencher o CheckboxList próprio. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $features = $data['features_custom'] ?? [];
        $data['relatorios_inclusos_custom'] = array_values(array_filter($features, fn ($f) => str_starts_with($f, 'rel_')));
        $data['features_custom'] = array_values(array_filter($features, fn ($f) => ! str_starts_with($f, 'rel_')));

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return TenantsResource::mesclarRelatoriosCustom($data);
    }
}
