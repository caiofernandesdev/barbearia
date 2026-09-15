<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\RepescagemStatsWidget;
use App\Jobs\EnviarWhatsAppJob;
use App\Models\Agendamento;
use App\Models\ConfiguracaoBarbearia;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RepescagemAvulsos extends Page implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions, InteractsWithSchemas, InteractsWithTable;

    protected string $view = 'filament.pages.repescagem-avulsos';

    protected static \BackedEnum|string|null $navigationIcon = Heroicon::OutlinedUserGroup;

    public static function getNavigationLabel(): string
    {
        return __('painel.nav.repescagem');
    }

    protected static ?string $title = 'Repescagem de Avulsos';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): string
    {
        return __('painel.group.clientes');
    }

    public int $diasSemAgendar = 30;

    protected function getHeaderWidgets(): array
    {
        return [
            RepescagemStatsWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 3;
    }

    public function getTableRecordKey(Model|array $record): string
    {
        return (string) (is_array($record) ? ($record['cliente_telefone'] ?? '') : $record->cliente_telefone);
    }

    public function getTableRecord(?string $key): Model|array|null
    {
        return Agendamento::query()
            ->select([
                'cliente_telefone',
                DB::raw('MAX(cliente_nome) as cliente_nome'),
                DB::raw('MAX(data_hora) as ultimo_agendamento'),
                DB::raw('DATEDIFF(NOW(), MAX(data_hora)) as dias_ausente'),
                DB::raw('COUNT(*) as total_agendamentos'),
            ])
            ->where('mensalista', false)
            ->whereNotIn('status', ['cancelado'])
            ->where('cliente_telefone', $key)
            ->groupBy('cliente_telefone')
            ->firstOrFail();
    }

    public static function canAccess(): bool
    {
        if (! auth()->user()?->temPermissao('repescagem')) {
            return false;
        }
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;

        return $tenant?->hasFeature('repescagem') ?? false;
    }

    private function getMensagemPadrao(): string
    {
        $config = ConfiguracaoBarbearia::getInstance();
        $nomeBarbearia = $config->nome_barbearia;
        $link = $this->getTenantLink();

        if (! empty($config->mensagem_repescagem)) {
            // {estabelecimento} é o novo nome; {barbearia} mantido por compatibilidade
            return str_replace(['{estabelecimento}', '{barbearia}', '{link}'], [$nomeBarbearia, $nomeBarbearia, $link], $config->mensagem_repescagem);
        }

        return implode("\n", [
            'Olá, {nome}! 👋',
            '',
            "Sentimos sua falta na *{$nomeBarbearia}*! ✨",
            '',
            'Que tal agendar um horário?',
            '',
            "Acesse: {$link}",
        ]);
    }

    private function getTenantLink(): string
    {
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;

        return $tenant ? url("/{$tenant->slug}") : url('/');
    }

    private function enviarMensagem(string $telefone, string $nome, string $mensagem): bool
    {
        $msg = str_replace('{nome}', $nome, $mensagem);
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;
        EnviarWhatsAppJob::dispatch($telefone, $msg, $tenant?->id);

        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getQuery())
            ->columns([
                TextColumn::make('cliente_nome')
                    ->label(__('painel.repescagem.col_cliente'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('cliente_telefone')
                    ->label(__('painel.repescagem.col_telefone')),

                TextColumn::make('ultimo_agendamento')
                    ->label(__('painel.repescagem.col_ultimo'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('dias_ausente')
                    ->label(__('painel.repescagem.col_dias'))
                    ->suffix(__('painel.repescagem.dias_suffix'))
                    ->sortable()
                    ->color(fn ($state) => $state > 60 ? 'danger' : ($state > 30 ? 'warning' : 'success')),

                TextColumn::make('total_agendamentos')
                    ->label(__('painel.repescagem.col_visitas'))
                    ->sortable(),
            ])
            ->defaultSort('dias_ausente', 'desc')
            ->headerActions([
                Action::make('filtro_dias')
                    ->label(fn () => __('painel.repescagem.filtro_label', ['dias' => $this->diasSemAgendar]))
                    ->icon('heroicon-o-funnel')
                    ->color('gray')
                    ->form([
                        Select::make('dias')
                            ->label(__('painel.repescagem.filtro_dias'))
                            ->options(collect([15, 30, 45, 60, 90])->mapWithKeys(fn ($n) => [$n => __('painel.repescagem.opt_dias', ['n' => $n])])->all())
                            ->default($this->diasSemAgendar),
                    ])
                    ->action(fn (array $data) => $this->diasSemAgendar = $data['dias']),
            ])
            ->recordActions([
                Action::make('enviar_whatsapp')
                    ->label(__('painel.repescagem.act_chamar'))
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(__('painel.repescagem.modal_enviar'))
                    ->modalDescription(fn ($record) => $record ? __('painel.repescagem.modal_desc', ['nome' => $record->cliente_nome, 'tel' => $record->cliente_telefone]) : '')
                    ->modalSubmitActionLabel(__('painel.repescagem.modal_submit'))
                    ->action(function ($record) {
                        if (! $record) {
                            return;
                        }
                        $enviado = $this->enviarMensagem($record->cliente_telefone, $record->cliente_nome, $this->getMensagemPadrao());
                        Notification::make()
                            ->title($enviado ? __('painel.repescagem.n_enviada') : __('painel.repescagem.n_falha'))
                            ->color($enviado ? 'success' : 'danger')
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('enviar_massa')
                    ->label(__('painel.repescagem.act_chamar'))
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->form(fn () => [
                        Textarea::make('mensagem')
                            ->label(__('painel.repescagem.msg_label'))
                            ->rows(5)
                            ->default($this->getMensagemPadrao())
                            ->required()
                            ->helperText(__('painel.repescagem.msg_help')),
                    ])
                    ->modalHeading(fn () => __('painel.repescagem.modal_massa', ['count' => count($this->selectedTableRecords)]))
                    ->modalSubmitActionLabel(__('painel.repescagem.modal_massa_submit'))
                    ->deselectRecordsAfterCompletion()
                    ->action(function (array $data) {
                        $telefones = $this->selectedTableRecords;
                        if (empty($telefones)) {
                            Notification::make()->title(__('painel.repescagem.n_nenhum'))->warning()->send();

                            return;
                        }

                        $records = Agendamento::query()
                            ->select(['cliente_telefone', DB::raw('MAX(cliente_nome) as cliente_nome')])
                            ->whereIn('cliente_telefone', $telefones)
                            ->groupBy('cliente_telefone')
                            ->get();

                        $ok = 0;
                        $falhas = 0;
                        foreach ($records as $record) {
                            if ($this->enviarMensagem($record->cliente_telefone, $record->cliente_nome ?? '', $data['mensagem'])) {
                                $ok++;
                            } else {
                                $falhas++;
                            }
                        }
                        if ($ok > 0) {
                            Notification::make()->title(__('painel.repescagem.n_enviadas', ['count' => $ok]))->success()->send();
                        }
                        if ($falhas > 0) {
                            Notification::make()->title(__('painel.repescagem.n_falhas', ['count' => $falhas]))->danger()->send();
                        }
                    }),
            ]);
    }

    private function getQuery(): Builder
    {
        return Agendamento::query()
            ->select([
                'cliente_telefone',
                DB::raw('MAX(cliente_nome) as cliente_nome'),
                DB::raw('MAX(data_hora) as ultimo_agendamento'),
                DB::raw('DATEDIFF(NOW(), MAX(data_hora)) as dias_ausente'),
                DB::raw('COUNT(*) as total_agendamentos'),
            ])
            ->where('mensalista', false)
            ->whereNotIn('status', ['cancelado'])
            ->groupBy('cliente_telefone')
            ->havingRaw('DATEDIFF(NOW(), MAX(data_hora)) >= ?', [$this->diasSemAgendar]);
    }
}
