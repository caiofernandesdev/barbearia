<?php

namespace App\Filament\Resources\Agendamentos\Tables;

use App\Filament\Support\AgendamentoTabela;
use App\Jobs\EnviarWhatsAppJob;
use App\Models\ConfiguracaoBarbearia;
use App\Observers\AgendamentoObserver;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class AgendamentosTable
{
    public static function configure(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $emGrade = method_exists($livewire, 'emGrade') && $livewire->emGrade();

        $table
            ->defaultSort('data_hora', 'desc')
            ->filters([
                Filter::make('data')
                    ->label(__('painel.agendamento.f_periodo'))
                    ->form([
                        DatePicker::make('data_inicio')
                            ->label(__('painel.agendamento.f_de'))
                            ->displayFormat('d/m/Y')
                            ->native(false),
                        DatePicker::make('data_fim')
                            ->label(__('painel.agendamento.f_ate'))
                            ->displayFormat('d/m/Y')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['data_inicio'], fn ($q) => $q->whereDate('data_hora', '>=', $data['data_inicio']))
                            ->when($data['data_fim'], fn ($q) => $q->whereDate('data_hora', '<=', $data['data_fim']));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['data_inicio'] ?? null) {
                            $indicators[] = __('painel.agendamento.ind_de').' '.Carbon::parse($data['data_inicio'])->format('d/m/Y');
                        }
                        if ($data['data_fim'] ?? null) {
                            $indicators[] = __('painel.agendamento.ind_ate').' '.Carbon::parse($data['data_fim'])->format('d/m/Y');
                        }

                        return $indicators;
                    }),

                Filter::make('hoje')
                    ->label(__('painel.agendamento.f_hoje'))
                    ->query(fn (Builder $query) => $query->whereDate('data_hora', now()->today())),

                SelectFilter::make('status')
                    ->label(__('painel.agendamento.status'))
                    ->options([
                        'pendente' => __('painel.agendamento.st_pendente'),
                        'confirmado' => __('painel.agendamento.st_confirmado'),
                        'concluido' => __('painel.agendamento.st_concluido'),
                        'cancelado' => __('painel.agendamento.st_cancelado'),
                    ]),

                SelectFilter::make('profissional_id')
                    ->label(__('painel.agendamento.profissional'))
                    ->relationship('profissional', 'nome'),

                Filter::make('mensalistas')
                    ->label(__('painel.agendamento.f_so_mensalistas'))
                    ->query(fn (Builder $query) => $query->where('mensalista', true)),

                Filter::make('avulso_mensalista_fixo')
                    ->label(__('painel.agendamento.f_avulso_fixo'))
                    ->query(fn (Builder $query) => $query->where('is_avulso_mensalista_fixo', true)),

                // Filtros dinâmicos por campo personalizado (respostas em dados_extras JSON)
                ...AgendamentoTabela::filtrosCamposExtras(),
            ])
            ->recordActions([
                Action::make('enviar_confirmacao')
                    ->label(__('painel.agendamento.act_pedir'))
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(__('painel.agendamento.act_modal_heading'))
                    ->modalDescription(fn ($record) => __('painel.agendamento.act_modal_desc', ['nome' => $record->cliente_nome, 'tel' => $record->cliente_telefone]))
                    ->modalSubmitActionLabel(__('painel.agendamento.act_enviar'))
                    // Sem módulo WhatsApp ativo não há confirmação por mensagem
                    ->visible(fn () => (app()->bound('current_tenant') ? app('current_tenant')?->whatsappAtivo() : false) ?? false)
                    ->hidden(fn ($record) => $record->status === 'cancelado')
                    ->action(function ($record) {
                        $nomeBarbearia = ConfiguracaoBarbearia::getInstance()->nome_barbearia;
                        $mensagem = AgendamentoObserver::mensagemLembrete($record, $nomeBarbearia);
                        EnviarWhatsAppJob::dispatch($record->cliente_telefone, $mensagem, $record->tenant_id);
                        Notification::make()->title(__('painel.agendamento.notif_enviada'))->body(__('painel.agendamento.notif_enviada_body', ['nome' => $record->cliente_nome]))->success()->send();
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkAction::make('enviar_confirmacao_massa')
                    ->label(__('painel.agendamento.act_pedir'))
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(__('painel.agendamento.act_modal_heading'))
                    ->modalDescription(fn (Collection $records) => __('painel.agendamento.act_bulk_desc', ['count' => $records->count()]))
                    ->modalSubmitActionLabel(__('painel.agendamento.act_enviar_todos'))
                    ->visible(fn () => (app()->bound('current_tenant') ? app('current_tenant')?->whatsappAtivo() : false) ?? false)
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records) {
                        $nomeBarbearia = ConfiguracaoBarbearia::getInstance()->nome_barbearia;
                        $enviados = 0;

                        foreach ($records as $record) {
                            if ($record->status === 'cancelado') {
                                continue;
                            }
                            $mensagem = AgendamentoObserver::mensagemLembrete($record, $nomeBarbearia);
                            EnviarWhatsAppJob::dispatch($record->cliente_telefone, $mensagem, $record->tenant_id);
                            $enviados++;
                        }

                        if ($enviados > 0) {
                            Notification::make()
                                ->title(__('painel.agendamento.notif_fila', ['count' => $enviados]))
                                ->success()
                                ->send();
                        }
                    }),

                DeleteBulkAction::make(),
            ]);

        return $emGrade
            ? self::configurarGrade($table)
            : self::configurarLista($table);
    }

    /** Badge do status — reaproveitado nos dois layouts. */
    private static function colunaStatus(): TextColumn
    {
        return TextColumn::make('status')
            ->label(__('painel.agendamento.status'))
            ->badge()
            ->formatStateUsing(fn (string $state): string => match ($state) {
                'pendente' => __('painel.agendamento.st_pendente'),
                'confirmado' => __('painel.agendamento.st_confirmado'),
                'concluido' => __('painel.agendamento.st_concluido'),
                'cancelado' => __('painel.agendamento.st_cancelado'),
                default => $state,
            })
            ->color(fn (string $state): string => match ($state) {
                'pendente' => 'warning',
                'confirmado' => 'success',
                'concluido' => 'info',
                'cancelado' => 'danger',
                default => 'gray',
            });
    }

    /** Layout em linhas — colunas secundárias somem no mobile. */
    protected static function configurarLista(Table $table): Table
    {
        return $table
            ->contentGrid(null)
            ->columns([
                TextColumn::make('cliente_nome')
                    ->label(__('painel.agendamento.col_cliente'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('cliente_telefone')
                    ->label(__('painel.agendamento.col_telefone'))
                    ->searchable()
                    ->visibleFrom('lg'),

                TextColumn::make('profissional.nome')
                    ->label(__('painel.agendamento.profissional'))
                    ->sortable()
                    ->visibleFrom('md'),

                TextColumn::make('servico.nome')
                    ->label(__('painel.agendamento.col_servico'))
                    ->getStateUsing(fn ($record) => $record->nomesServicos())
                    ->description(fn ($record) => 'R$ '.number_format((float) ($record->valor_total ?? $record->servico?->preco ?? 0), 2, ',', '.'))
                    ->sortable()
                    ->visibleFrom('sm'),

                TextColumn::make('data_hora')
                    ->label(__('painel.agendamento.data_hora'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                self::colunaStatus(),

                IconColumn::make('mensalista')
                    ->label(__('painel.agendamento.mensalista'))
                    ->boolean()
                    ->visibleFrom('lg'),

                IconColumn::make('is_avulso_mensalista_fixo')
                    ->label(__('painel.agendamento.col_avulso_fixo'))
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->tooltip(__('painel.agendamento.tip_avulso_fixo'))
                    ->visibleFrom('lg'),

                AgendamentoTabela::colunaDetalhes(),

                TextColumn::make('created_at')
                    ->label(__('painel.agendamento.col_criado'))
                    ->date('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ]);
    }

    /** Layout em cards (grade responsiva) — ótimo no celular. */
    protected static function configurarGrade(Table $table): Table
    {
        return $table
            ->contentGrid(['default' => 1, 'sm' => 2, 'lg' => 3])
            ->columns([
                Stack::make([
                    TextColumn::make('cliente_nome')
                        ->searchable()
                        ->weight('bold')
                        ->size('lg'),

                    TextColumn::make('data_hora')
                        ->dateTime('d/m/Y H:i')
                        ->icon('heroicon-m-calendar-days')
                        ->color('gray'),

                    TextColumn::make('servico.nome')
                        ->getStateUsing(fn ($record) => $record->nomesServicos())
                        ->description(fn ($record) => 'R$ '.number_format((float) ($record->valor_total ?? $record->servico?->preco ?? 0), 2, ',', '.'))
                        ->icon('heroicon-m-scissors')
                        ->color('gray'),

                    TextColumn::make('profissional.nome')
                        ->icon('heroicon-m-user')
                        ->color('gray'),

                    self::colunaStatus(),
                ])
                    ->space(2),
            ]);
    }
}
