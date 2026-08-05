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
                    ->label('Período')
                    ->form([
                        DatePicker::make('data_inicio')
                            ->label('De')
                            ->displayFormat('d/m/Y')
                            ->native(false),
                        DatePicker::make('data_fim')
                            ->label('Até')
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
                            $indicators[] = 'De: '.Carbon::parse($data['data_inicio'])->format('d/m/Y');
                        }
                        if ($data['data_fim'] ?? null) {
                            $indicators[] = 'Até: '.Carbon::parse($data['data_fim'])->format('d/m/Y');
                        }

                        return $indicators;
                    }),

                Filter::make('hoje')
                    ->label('Hoje')
                    ->query(fn (Builder $query) => $query->whereDate('data_hora', now()->today())),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pendente' => 'Pendente',
                        'confirmado' => 'Confirmado',
                        'concluido' => 'Concluído',
                        'cancelado' => 'Cancelado',
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

                // Filtros dinâmicos por campo personalizado (respostas em dados_extras JSON)
                ...AgendamentoTabela::filtrosCamposExtras(),
            ])
            ->recordActions([
                Action::make('enviar_confirmacao')
                    ->label('Pedir confirmação')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Pedir confirmação por WhatsApp?')
                    ->modalDescription(fn ($record) => "Enviar mensagem pedindo confirmação para {$record->cliente_nome} ({$record->cliente_telefone})?")
                    ->modalSubmitActionLabel('Enviar')
                    // Sem módulo WhatsApp ativo não há confirmação por mensagem
                    ->visible(fn () => (app()->bound('current_tenant') ? app('current_tenant')?->whatsappAtivo() : false) ?? false)
                    ->hidden(fn ($record) => $record->status === 'cancelado')
                    ->action(function ($record) {
                        $nomeBarbearia = ConfiguracaoBarbearia::getInstance()->nome_barbearia;
                        $mensagem = AgendamentoObserver::mensagemLembrete($record, $nomeBarbearia);
                        EnviarWhatsAppJob::dispatch($record->cliente_telefone, $mensagem, $record->tenant_id);
                        Notification::make()->title('Mensagem enviada!')->body("Confirmação enviada para {$record->cliente_nome}.")->success()->send();
                    }),

                EditAction::make(),
                DeleteAction::make()->label('Excluir'),
            ])
            ->toolbarActions([
                BulkAction::make('enviar_confirmacao_massa')
                    ->label('Pedir confirmação')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Pedir confirmação por WhatsApp?')
                    ->modalDescription(fn (Collection $records) => "Enviar mensagem de confirmação para {$records->count()} agendamento(s) selecionado(s)?")
                    ->modalSubmitActionLabel('Enviar para todos')
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
                                ->title("$enviados mensagem(ns) na fila!")
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
            ->label('Status')
            ->badge()
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
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('cliente_telefone')
                    ->label('Telefone')
                    ->searchable()
                    ->visibleFrom('lg'),

                TextColumn::make('profissional.nome')
                    ->label('Profissional')
                    ->sortable()
                    ->visibleFrom('md'),

                TextColumn::make('servico.nome')
                    ->label('Serviço')
                    ->getStateUsing(fn ($record) => $record->nomesServicos())
                    ->description(fn ($record) => 'R$ '.number_format((float) ($record->valor_total ?? $record->servico?->preco ?? 0), 2, ',', '.'))
                    ->sortable()
                    ->visibleFrom('sm'),

                TextColumn::make('data_hora')
                    ->label('Data e Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                self::colunaStatus(),

                IconColumn::make('mensalista')
                    ->label('Mensalista')
                    ->boolean()
                    ->visibleFrom('lg'),

                IconColumn::make('is_avulso_mensalista_fixo')
                    ->label('⚠ Avulso Fixo')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->tooltip('Mensalista Fixo que agendou fora do horário fixo')
                    ->visibleFrom('lg'),

                AgendamentoTabela::colunaDetalhes(),

                TextColumn::make('created_at')
                    ->label('Criado em')
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
