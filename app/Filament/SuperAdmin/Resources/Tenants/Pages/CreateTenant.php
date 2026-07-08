<?php

namespace App\Filament\SuperAdmin\Resources\Tenants\Pages;

use App\Filament\SuperAdmin\Resources\Tenants\TenantsResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantsResource::class;

    private array $adminData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->adminData = [
            'nome'  => $data['admin_nome'] ?? null,
            'email' => $data['admin_email'] ?? null,
            'senha' => $data['admin_senha'] ?? null,
        ];

        unset($data['admin_nome'], $data['admin_email'], $data['admin_senha']);
        return $data;
    }

    protected function afterCreate(): void
    {
        if (! empty($this->adminData['email'])) {
            User::withoutGlobalScopes()->create([
                'name'      => $this->adminData['nome'],
                'email'     => $this->adminData['email'],
                'password'  => Hash::make($this->adminData['senha']),
                'role'      => 'admin',
                'tenant_id' => $this->record->id,
            ]);
        }
    }
}
