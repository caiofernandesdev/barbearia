<?php

namespace App\Filament\Resources\Agendamentos\Tables;

use App\Models\ConfiguracaoBarbearia;
use App\Observers\AgendamentoObserver;
use App\Services\WhatsAppService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AgendamentosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('cliente_nome')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('cliente_telefone')
                    ->label('Telefone')
                    ->searchable(),

                TextColumn::make('profissional.nome')
                    ->label('Profissional')
                    ->sortable(),

                TextColumn::make('servico.nome')
                    ->label('Serviço')
                    ->sortable(),

                TextColumn::make('data_hora')
                    ->label('Data e Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pendente'   => 'warning',
                        'confirmado' => 'success',
                        'concluido'  => 'info',
                        'cancelado'  => 'danger',
                        default      => 'gray',
                    }),

                IconColumn::make('mensalista')
                    ->label('Mensalista')
                    ->boolean(),

                IconColumn::make('is_avulso_mensalista_fixo')
                    ->label('⚠ Avulso Fixo')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->tooltip('Mensalista Fixo que agendou fora do horário fixo'),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->date('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('data_hora', 'desc')
            ->filters([
                Filter::make('hoje')
                    ->label('Hoje')
                    ->query(fn (Builder $query) => $query->whereDate('data_hora', now()->today())),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pendente'   => 'Pendente',
                        'confirmado' => 'Confirmado',
                        'concluido'  => 'Concluído',
                        'cancelado'  => 'Cancelado',
                    ]),

                SelectFilter::make('profissional_id')
                    ->label('Profissional')
                    ->relationship('profissional', 'nome'),

                Filter::make('mensalistas')
                    ->label('Apenas mensalistas')
                    ->query(fn (Builder $query) => $query->where('mensalista', true)),

                Filter::make('avulso_mensalista_fixo')
                    ->label('⚠ Avulso fora do horário fixo')
                    ->query(fn (Builder $query) => $query->where('is_avulso_mensalista_fixo', true)),
            ])
            ->recordActions([
                Action::make('enviar_confirmacao')
                    ->label('Enviar confirmação')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Enviar confirmação por WhatsApp?')
                    ->modalDescription(fn ($record) => "Enviar mensagem de confirmação para {$record->cliente_nome} ({$record->cliente_telefone})?")
                    ->modalSubmitActionLabel('Enviar')
                    ->hidden(fn ($record) => $record->status === 'cancelado')
                    ->action(function ($record) {
                        $nomeBarbearia = ConfiguracaoBarbearia::getInstance()->nome_barbearia;
                        $mensagem      = AgendamentoObserver::mensagemConfirmado($record, $nomeBarbearia);
                        $enviado       = app(WhatsAppService::class)->enviarTexto($record->cliente_telefone, $mensagem);

                        if ($enviado) {
                            Notification::make()
                                ->title('Mensagem enviada!')
                                ->body("Confirmação enviada para {$record->cliente_nome}.")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Falha ao enviar')
                                ->body('Verifique as configurações do WhatsApp (ZAPI_CLIENT_TOKEN).')
                                ->danger()
                                ->send();
                        }
                    }),

                EditAction::make(),
                DeleteAction::make()->label('Excluir'),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
