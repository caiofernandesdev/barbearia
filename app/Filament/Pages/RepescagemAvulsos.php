<?php

namespace App\Filament\Pages;

use App\Models\Agendamento;
use App\Models\ConfiguracaoBarbearia;
use App\Observers\AgendamentoObserver;
use App\Services\WhatsAppService;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RepescagemAvulsos extends Page implements HasTable, HasActions, HasSchemas
{
    use InteractsWithTable, InteractsWithActions, InteractsWithSchemas;

    protected string $view = 'filament.pages.repescagem-avulsos';

    protected static \BackedEnum|string|null $navigationIcon = Heroicon::OutlinedUserGroup;
    protected static ?string $navigationLabel = 'Repescagem';
    protected static ?string $title           = 'Repescagem de Avulsos';
    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): string
    {
        return 'Clientes';
    }

    public int $diasSemAgendar = 30;

    public function getTableRecordKey(\Illuminate\Database\Eloquent\Model|array $record): string
    {
        $telefone = is_array($record) ? ($record['cliente_telefone'] ?? '') : $record->cliente_telefone;
        return (string) $telefone;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
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
                    ->label('Total de visitas')
                    ->sortable(),
            ])
            ->defaultSort('dias_ausente', 'desc')
            ->headerActions([
                \Filament\Actions\Action::make('filtro_dias')
                    ->label(fn () => "Ausentes há mais de {$this->diasSemAgendar} dias")
                    ->icon('heroicon-o-funnel')
                    ->color('gray')
                    ->form([
                        \Filament\Forms\Components\Select::make('dias')
                            ->label('Ausentes há mais de')
                            ->options([
                                15 => '15 dias',
                                30 => '30 dias',
                                45 => '45 dias',
                                60 => '60 dias',
                                90 => '90 dias',
                            ])
                            ->default($this->diasSemAgendar),
                    ])
                    ->action(fn (array $data) => $this->diasSemAgendar = $data['dias']),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('enviar_whatsapp')
                    ->label('Chamar de volta')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Enviar mensagem de repescagem?')
                    ->modalDescription(fn ($record) => "Enviar mensagem para {$record['cliente_nome']} ({$record['cliente_telefone']})?")
                    ->modalSubmitActionLabel('Enviar')
                    ->action(function ($record) {
                        $nomeBarbearia = ConfiguracaoBarbearia::getInstance()->nome_barbearia;
                        $mensagem      = $this->mensagemRepescagem($record['cliente_nome'], $nomeBarbearia);
                        $enviado       = app(WhatsAppService::class)->enviarTexto($record['cliente_telefone'], $mensagem);

                        if ($enviado) {
                            Notification::make()->title('Mensagem enviada!')->success()->send();
                        } else {
                            Notification::make()->title('Falha ao enviar')->danger()->send();
                        }
                    }),
            ]);
    }

    private function getQuery(): Builder
    {
        $diasSemAgendar = $this->diasSemAgendar;

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
            ->havingRaw('DATEDIFF(NOW(), MAX(data_hora)) >= ?', [$diasSemAgendar]);
    }

    private function mensagemRepescagem(string $nomeCliente, string $nomeBarbearia): string
    {
        return implode("\n", [
            "Olá, {$nomeCliente}! 👋",
            "",
            "Sentimos sua falta na *{$nomeBarbearia}*! 💈",
            "",
            "Que tal agendar um horário? Estamos te esperando!",
            "",
            "Acesse: " . url('/'),
        ]);
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
