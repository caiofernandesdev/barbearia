<?php

namespace App\Filament\SuperAdmin\Pages;

use App\Models\Tenant;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;

/**
 * Mensalidades de um tenant, mês a mês. Cada competência aparece como paga
 * (com data e comprovante) ou em aberto; o mês da vez abre a caixa de
 * pagamento. Chega-se aqui pela página Financeiro.
 */
class Mensalidades extends Page implements HasActions, HasSchemas
{
    use InteractsWithActions, InteractsWithSchemas;

    protected string $view = 'filament.super-admin.pages.mensalidades';

    protected static bool $shouldRegisterNavigation = false;

    public ?int $tenantId = null;

    public ?Tenant $tenant = null;

    public function mount(): void
    {
        $this->tenantId = request()->integer('tenant');
        $this->tenant = Tenant::with('plano')->find($this->tenantId);

        abort_unless($this->tenant, 404);
    }

    public function getTitle(): string
    {
        return 'Mensalidades — '.$this->tenant->nome;
    }

    public function getMesesProperty(): array
    {
        return $this->tenant->mesesCobranca();
    }

    /** Modal de pagamento — a "caixa" que abre ao clicar no mês */
    public function registrarPagamentoAction(): Action
    {
        return Action::make('registrarPagamento')
            ->modalHeading(fn (array $arguments) => 'Pagamento — '.$this->rotuloMes($arguments['competencia'] ?? ''))
            ->modalSubmitActionLabel('Registrar pagamento')
            ->schema([
                TextInput::make('valor')
                    ->label('Valor recebido (R$)')
                    ->numeric()
                    ->required()
                    ->default(fn () => $this->tenant->valorMensal()),

                Select::make('forma')
                    ->label('Forma')
                    ->options([
                        'pix' => 'PIX',
                        'dinheiro' => 'Dinheiro',
                        'cartao' => 'Cartão',
                        'transferencia' => 'Transferência',
                        'outro' => 'Outro',
                    ])
                    ->default('pix')
                    ->required(),

                Textarea::make('observacao')
                    ->label('Observação')
                    ->rows(2)
                    ->placeholder('Opcional'),

                FileUpload::make('comprovante')
                    ->label('Comprovante')
                    ->disk('local')
                    ->directory('comprovantes')
                    ->visibility('private')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                    ->maxSize(10240)
                    ->helperText('Imagem ou PDF, até 10 MB. Opcional.'),
            ])
            ->action(function (array $data, array $arguments) {
                // Segurança: só o mês da vez pode ser pago (evita pular a fila)
                $competenciaDaVez = ($this->tenant->proximo_vencimento ?? now())->format('Y-m');
                if (($arguments['competencia'] ?? null) !== $competenciaDaVez) {
                    Notification::make()
                        ->title('Pague os meses em ordem')
                        ->body('Este mês ainda não é a vez.')
                        ->warning()
                        ->send();

                    return;
                }

                $this->tenant->registrarPagamento(
                    (float) $data['valor'],
                    $data['forma'],
                    $data['observacao'] ?? null,
                    $data['comprovante'] ?? null,
                );

                $this->tenant->refresh();

                Notification::make()
                    ->title('Pagamento registrado')
                    ->body('Próximo vencimento: '.$this->tenant->proximo_vencimento->format('d/m/Y'))
                    ->success()
                    ->send();
            });
    }

    private function rotuloMes(string $competencia): string
    {
        if (! $competencia) {
            return '';
        }

        return Carbon::parse($competencia.'-01')->locale('pt_BR')->isoFormat('MMMM [de] YYYY');
    }
}
