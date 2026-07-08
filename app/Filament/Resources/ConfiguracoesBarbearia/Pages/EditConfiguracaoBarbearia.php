<?php

namespace App\Filament\Resources\ConfiguracoesBarbearia\Pages;

use App\Filament\Resources\ConfiguracoesBarbearia\ConfiguracaoBarbeariaResource;
use App\Models\ConfiguracaoBarbearia;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditConfiguracaoBarbearia extends EditRecord
{
    protected static string $resource = ConfiguracaoBarbeariaResource::class;

    protected static ?string $title = 'Configurações da Barbearia';

    /**
     * Ignora o parâmetro de rota e sempre carrega o registro singleton.
     */
    public function mount(int | string | null $record = null): void
    {
        $this->record = ConfiguracaoBarbearia::getInstance();
        $this->fillForm();
    }

    protected function getHeaderActions(): array
    {
        return []; // Não exibe botão de excluir em configurações
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Configurações salvas com sucesso!');
    }
}
