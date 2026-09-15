<?php

namespace App\Filament\Resources\Mensalistas\Pages;

use App\Exports\MensalistasExport;
use App\Exports\MensalistasModeloExport;
use App\Filament\Concerns\TogglesTableLayout;
use App\Filament\Resources\Mensalistas\MensalistaResource;
use App\Imports\MensalistasImport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Illuminate\Support\HtmlString;
use Maatwebsite\Excel\Facades\Excel;

class ListMensalistas extends ListRecords
{
    use TogglesTableLayout;

    protected static string $resource = MensalistaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->layoutToggleAction(),

            Action::make('exportar')
                ->label(__('painel.cliente.exportar'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => Excel::download(new MensalistasExport, 'mensalistas.xlsx')),

            Action::make('importar')
                ->label(__('painel.cliente.importar'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->modalHeading(__('painel.cliente.imp_heading'))
                ->modalDescription(__('painel.cliente.imp_desc'))
                ->modalWidth(Width::Large)
                ->modalSubmitActionLabel(__('painel.cliente.imp_submit'))
                ->form([
                    // Instruções + link de modelo dentro do próprio modal.
                    Placeholder::make('instrucoes')
                        ->label(__('painel.cliente.imp_instr_label'))
                        ->content(new HtmlString(
                            '<div class="text-sm space-y-2">'
                            .'<p>'.__('painel.cliente.imp_intro').'</p>'
                            .'<ul class="list-disc list-inside space-y-1">'
                            .'<li>'.__('painel.cliente.imp_li_nome').'</li>'
                            .'<li>'.__('painel.cliente.imp_li_telefone').'</li>'
                            .'<li>'.__('painel.cliente.imp_li_tipo').'</li>'
                            .'<li>'.__('painel.cliente.imp_li_limite').'</li>'
                            .'<li>'.__('painel.cliente.imp_li_valor').'</li>'
                            .'</ul>'
                            .'<p class="text-gray-500">'.__('painel.cliente.imp_nota').'</p>'
                            .'</div>'
                        )),

                    FileUpload::make('arquivo')
                        ->label(__('painel.cliente.imp_file'))
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'text/csv',
                            'application/csv',
                        ])
                        ->required()
                        ->storeFiles(false),
                ])
                // Botão extra no rodapé do modal para baixar o modelo pronto.
                ->extraModalFooterActions([
                    Action::make('baixar_modelo')
                        ->label(__('painel.cliente.imp_baixar_modelo'))
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('gray')
                        ->action(fn () => Excel::download(new MensalistasModeloExport, 'modelo-mensalistas.xlsx')),
                ])
                ->action(function (array $data) {
                    try {
                        $import = new MensalistasImport;
                        Excel::import($import, $data['arquivo']);

                        $importados = $import->importados;
                        $duplicados = $import->duplicados;
                        $invalidas = $import->invalidas();

                        $partes = [__('painel.cliente.imp_importados', ['count' => $importados])];
                        if ($duplicados > 0) {
                            $partes[] = __('painel.cliente.imp_ja_existia', ['count' => $duplicados]);
                        }
                        if ($invalidas > 0) {
                            $partes[] = __('painel.cliente.imp_invalidas', ['count' => $invalidas]);
                        }

                        Notification::make()
                            ->title($importados > 0 ? __('painel.cliente.imp_ok') : __('painel.cliente.imp_nada'))
                            ->body(implode(' · ', $partes))
                            ->color($importados > 0 ? 'success' : 'warning')
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title(__('painel.cliente.imp_erro'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            CreateAction::make(),
        ];
    }
}
