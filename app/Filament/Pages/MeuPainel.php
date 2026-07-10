<?php

namespace App\Filament\Pages;

use App\Jobs\EnviarWhatsAppJob;
use App\Models\Agendamento;
use App\Models\ConfiguracaoBarbearia;
use App\Observers\AgendamentoObserver;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class MeuPainel extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.meu-painel';

    protected static ?string $navigationLabel = 'Meu Painel';

    protected static ?string $title = 'Meu Painel';

    protected static \BackedEnum|string|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->isBarbeiro() ?? false;
    }

    public function table(Table $table): Table
    {
        $pid = auth()->user()->profissional_id;

        return $table
            ->query(
                Agendamento::query()
                    ->where('data_hora', '>=', now()->startOfDay())
                    ->when($pid, fn ($q) => $q->where('profissional_id', $pid))
                    ->with(['servico'])
                    ->orderBy('data_hora')
            )
            ->heading('Próximos Atendimentos')
            ->emptyStateHeading('Nenhum atendimento pendente')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pendente' => 'Pendente',
                        'confirmado' => 'Confirmado',
                        'concluido' => 'Concluído',
                        'cancelado' => 'Cancelado',
                    ])
                    ->default('pendente'),
            ])
            ->columns([
                TextColumn::make('data_hora')
                    ->label('Quando')
                    ->dateTime('d/m H:i')
                    ->weight(FontWeight::SemiBold)
                    ->description(fn ($record) => $record->data_hora->isToday() ? 'Hoje' : ($record->data_hora->isTomorrow() ? 'Amanhã' : $record->data_hora->locale('pt_BR')->isoFormat('dddd'))),

                TextColumn::make('cliente_nome')
                    ->label('Cliente')
                    ->description(fn ($record) => $record->cliente_telefone)
                    ->searchable(),

                TextColumn::make('servico.nome')
                    ->label('Serviço')
                    ->getStateUsing(fn ($record) => $record->nomesServicos())
                    ->description(fn ($record) => 'R$ '.number_format((float) ($record->valor_total ?? $record->servico?->preco ?? 0), 2, ',', '.')),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'pendente' => 'warning',
                        'confirmado' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pendente' => 'Pendente',
                        'confirmado' => 'Confirmado',
                        default => ucfirst($state),
                    }),
            ])
            ->recordActions([
                Action::make('pedir_confirmacao')
                    ->label('Confirmar')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Pedir confirmação por WhatsApp?')
                    ->modalDescription(fn ($record) => "Enviar para {$record->cliente_nome} ({$record->cliente_telefone})?")
                    ->modalSubmitActionLabel('Enviar')
                    ->hidden(fn ($record) => $record->status !== 'pendente')
                    ->action(function ($record) {
                        $nomeBarbearia = ConfiguracaoBarbearia::getInstance()->nome_barbearia;
                        $msg = AgendamentoObserver::mensagemLembrete($record, $nomeBarbearia);
                        EnviarWhatsAppJob::dispatch($record->cliente_telefone, $msg, $record->tenant_id);
                        Notification::make()->title('Mensagem enviada!')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('confirmar_massa')
                    ->label('Pedir confirmação')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Pedir confirmação em massa?')
                    ->modalDescription(fn (Collection $records) => "Enviar para {$records->count()} agendamento(s)?")
                    ->modalSubmitActionLabel('Enviar para todos')
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records) {
                        $nomeBarbearia = ConfiguracaoBarbearia::getInstance()->nome_barbearia;
                        $ok = 0;
                        foreach ($records as $r) {
                            if ($r->status !== 'pendente') {
                                continue;
                            }
                            $msg = AgendamentoObserver::mensagemLembrete($r, $nomeBarbearia);
                            EnviarWhatsAppJob::dispatch($r->cliente_telefone, $msg, $r->tenant_id);
                            $ok++;
                        }
                        Notification::make()->title("$ok mensagem(ns) na fila")->success()->send();
                    }),
            ])
            ->striped()
            ->defaultPaginationPageOption(15);
    }
}
