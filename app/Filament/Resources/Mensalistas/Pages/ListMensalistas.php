<?php

namespace App\Filament\Resources\Mensalistas\Pages;

use App\Exports\MensalistasExport;
use App\Exports\MensalistasModeloExport;
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
                ->modalHeading('Importar mensalistas')
                ->modalDescription('Envie uma planilha .xlsx ou .csv com seus clientes. Cada linha vira um mensalista.')
                ->modalWidth(Width::Large)
                ->modalSubmitActionLabel('Importar agora')
                ->form([
                    // Instruções + link de modelo dentro do próprio modal.
                    Placeholder::make('instrucoes')
                        ->label('Como montar a planilha')
                        ->content(new HtmlString(
                            '<div class="text-sm space-y-2">'
                            .'<p>A primeira linha deve conter os <strong>cabeçalhos</strong> exatamente com estes nomes:</p>'
                            .'<ul class="list-disc list-inside space-y-1">'
                            .'<li><code>nome</code> <span class="text-danger-600">(obrigatório)</span></li>'
                            .'<li><code>telefone</code> <span class="text-danger-600">(obrigatório)</span> — só números</li>'
                            .'<li><code>tipo</code> — <code>avulso</code>, <code>mensalista</code> ou <code>mensalista_fixo</code> (padrão: avulso)</li>'
                            .'<li><code>limite_cortes_semana</code> — número (padrão: 1)</li>'
                            .'<li><code>valor_mensalidade</code> — ex.: 99,90</li>'
                            .'</ul>'
                            .'<p class="text-gray-500">Clientes com telefone já cadastrado são ignorados (não duplica).</p>'
                            .'</div>'
                        )),

                    FileUpload::make('arquivo')
                        ->label('Planilha (.xlsx ou .csv)')
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
                        ->label('Baixar modelo')
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

                        $partes = ["{$importados} importado(s)"];
                        if ($duplicados > 0) {
                            $partes[] = "{$duplicados} já existia(m)";
                        }
                        if ($invalidas > 0) {
                            $partes[] = "{$invalidas} linha(s) inválida(s)";
                        }

                        Notification::make()
                            ->title($importados > 0 ? 'Importação concluída!' : 'Nada novo importado')
                            ->body(implode(' · ', $partes))
                            ->color($importados > 0 ? 'success' : 'warning')
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
