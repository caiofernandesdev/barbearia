<?php

namespace App\Filament\Resources\Mensalistas\Pages;

use App\Exports\MensalistasExport;
use App\Filament\Resources\Mensalistas\MensalistaResource;
use App\Imports\MensalistasImport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListMensalistas extends ListRecords
{
    protected static string $resource = MensalistaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportar')
                ->label('Exportar Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => Excel::download(new MensalistasExport, 'mensalistas.xlsx')),

            Action::make('importar')
                ->label('Importar Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->form([
                    FileUpload::make('arquivo')
                        ->label('Planilha (.xlsx ou .csv)')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'text/csv',
                        ])
                        ->required()
                        ->storeFiles(false),
                ])
                ->action(function (array $data) {
                    try {
                        Excel::import(new MensalistasImport, $data['arquivo']);

                        Notification::make()
                            ->title('Importação concluída!')
                            ->body('Mensalistas importados com sucesso.')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Erro na importação')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            CreateAction::make(),
        ];
    }
}
