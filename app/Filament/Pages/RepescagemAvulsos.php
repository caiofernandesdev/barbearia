<?php

namespace App\Filament\Pages;

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

    protected static ?string $navigationLabel = 'Repescagem';

    protected static ?string $title = 'Repescagem de Avulsos';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): string
    {
        return 'Clientes';
    }

    public int $diasSemAgendar = 30;

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
        if (! auth()->user()?->isAdmin()) {
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
            return str_replace(['{barbearia}', '{link}'], [$nomeBarbearia, $link], $config->mensagem_repescagem);
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
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('cliente_telefone')
                    ->label('Telefone'),

                TextColumn::make('ultimo_agendamento')
                    ->label('Último agendamento')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('dias_ausente')
                    ->label('Dias ausente')
                    ->suffix(' dias')
                    ->sortable()
                    ->color(fn ($state) => $state > 60 ? 'danger' : ($state > 30 ? 'warning' : 'success')),

                TextColumn::make('total_agendamentos')
                    ->label('Visitas')
                    ->sortable(),
            ])
            ->defaultSort('dias_ausente', 'desc')
            ->headerActions([
                Action::make('filtro_dias')
                    ->label(fn () => "Ausentes há mais de {$this->diasSemAgendar} dias")
                    ->icon('heroicon-o-funnel')
                    ->color('gray')
                    ->form([
                        Select::make('dias')
                            ->label('Ausentes há mais de')
                            ->options([15 => '15 dias', 30 => '30 dias', 45 => '45 dias', 60 => '60 dias', 90 => '90 dias'])
                            ->default($this->diasSemAgendar),
                    ])
                    ->action(fn (array $data) => $this->diasSemAgendar = $data['dias']),
            ])
            ->recordActions([
                Action::make('enviar_whatsapp')
                    ->label('Chamar de volta')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Enviar mensagem de repescagem?')
                    ->modalDescription(fn ($record) => $record ? "Enviar mensagem para {$record->cliente_nome} ({$record->cliente_telefone})?" : '')
                    ->modalSubmitActionLabel('Enviar')
                    ->action(function ($record) {
                        if (! $record) {
                            return;
                        }
                        $enviado = $this->enviarMensagem($record->cliente_telefone, $record->cliente_nome, $this->getMensagemPadrao());
                        Notification::make()
                            ->title($enviado ? 'Mensagem enviada!' : 'Falha no envio')
                            ->color($enviado ? 'success' : 'danger')
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('enviar_massa')
                    ->label('Chamar de volta')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->form(fn () => [
                        Textarea::make('mensagem')
                            ->label('Mensagem')
                            ->rows(5)
                            ->default($this->getMensagemPadrao())
                            ->required()
                            ->helperText('{nome} será substituído pelo nome de cada cliente'),
                    ])
                    ->modalHeading(fn () => 'Chamar de volta '.count($this->selectedTableRecords).' cliente(s)')
                    ->modalSubmitActionLabel('Enviar para todos')
                    ->deselectRecordsAfterCompletion()
                    ->action(function (array $data) {
                        $telefones = $this->selectedTableRecords;
                        if (empty($telefones)) {
                            Notification::make()->title('Nenhum cliente selecionado')->warning()->send();

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
                            Notification::make()->title("$ok mensagem(ns) enviada(s)!")->success()->send();
                        }
                        if ($falhas > 0) {
                            Notification::make()->title("$falhas falha(s)")->danger()->send();
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
