<?php

namespace App\Filament\SuperAdmin\Pages;

use App\Models\Pagamento;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Retaguarda financeira do SaaS: quanto entra por mês, quem está atrasado,
 * e o registro dos pagamentos das assinaturas dos tenants.
 */
class Financeiro extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.super-admin.pages.financeiro';

    protected static ?string $navigationLabel = 'Financeiro';

    protected static ?string $title = 'Financeiro';

    protected static \BackedEnum|string|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 0;

    public static function getNavigationGroup(): string
    {
        return 'Financeiro';
    }

    // ─── Resumo (stat cards nativos) ─────────────────────────────────────────

    public function resumoSchema(Schema $schema): Schema
    {
        $tenants = Tenant::with('plano')->get();
        $ativos = $tenants->where('ativo', true);

        $mrr = $ativos->sum(fn (Tenant $t) => $t->valorMensal());
        $atrasados = $ativos->filter(fn (Tenant $t) => $t->estaAtrasado());
        $valorAtrasado = $atrasados->sum(fn (Tenant $t) => $t->valorMensal());

        $recebidoMes = (float) Pagamento::whereYear('pago_em', now()->year)
            ->whereMonth('pago_em', now()->month)
            ->sum('valor');

        return $schema->components([
            Stat::make('Receita recorrente (MRR)', 'R$ '.number_format($mrr, 2, ',', '.'))
                ->description($ativos->count().' assinatura(s) ativa(s)')
                ->icon(Heroicon::OutlinedArrowTrendingUp)
                ->color('success'),

            Stat::make('Recebido este mês', 'R$ '.number_format($recebidoMes, 2, ',', '.'))
                ->description(now()->locale('pt_BR')->isoFormat('MMMM [de] YYYY'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('info'),

            Stat::make('Em atraso', 'R$ '.number_format($valorAtrasado, 2, ',', '.'))
                ->description($atrasados->count().' tenant(s) atrasado(s)')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color($atrasados->isEmpty() ? 'gray' : 'danger'),

            Stat::make('Total de tenants', (string) $tenants->count())
                ->description($tenants->where('ativo', false)->count().' inativo(s)')
                ->icon(Heroicon::OutlinedBuildingStorefront)
                ->color('warning'),
        ])->columns(4);
    }

    // ─── Tabela de assinaturas ────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        return $table
            ->query(Tenant::query()->with('plano'))
            ->heading('Assinaturas')
            ->description('Situação de cobrança de cada estabelecimento')
            ->defaultSort('proximo_vencimento', 'asc')
            ->columns([
                TextColumn::make('nome')
                    ->label('Estabelecimento')
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (Tenant $record) => '/'.$record->slug)
                    ->searchable(),

                TextColumn::make('plano.nome')
                    ->label('Plano')
                    ->badge()
                    ->color('gray')
                    ->placeholder('sem plano'),

                TextColumn::make('valorMensal')
                    ->label('Mensalidade')
                    ->getStateUsing(fn (Tenant $record) => 'R$ '.number_format($record->valorMensal(), 2, ',', '.'))
                    ->alignEnd(),

                TextColumn::make('proximo_vencimento')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->getStateUsing(fn (Tenant $record) => match ($record->statusCobranca()) {
                        'atrasado' => 'Atrasado '.$record->diasAtraso().'d',
                        'vence_em_breve' => 'Vence em breve',
                        'cortesia' => 'Cortesia',
                        default => 'Em dia',
                    })
                    ->color(fn (Tenant $record) => match ($record->statusCobranca()) {
                        'atrasado' => 'danger',
                        'vence_em_breve' => 'warning',
                        'cortesia' => 'gray',
                        default => 'success',
                    }),

                TextColumn::make('ativo')
                    ->label('Ativo')
                    ->badge()
                    ->getStateUsing(fn (Tenant $record) => $record->ativo ? 'Sim' : 'Não')
                    ->color(fn (Tenant $record) => $record->ativo ? 'success' : 'gray'),
            ])
            ->recordActions([
                Action::make('registrar_pagamento')
                    ->label('Registrar pagamento')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (Tenant $record) => $record->valorMensal() > 0)
                    ->schema([
                        TextInput::make('valor')
                            ->label('Valor recebido (R$)')
                            ->numeric()
                            ->required()
                            ->default(fn (Tenant $record) => $record->valorMensal()),

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
                    ])
                    ->modalHeading(fn (Tenant $record) => "Pagamento — {$record->nome}")
                    ->modalSubmitActionLabel('Registrar')
                    ->action(function (Tenant $record, array $data) {
                        $record->registrarPagamento(
                            (float) $data['valor'],
                            $data['forma'],
                            $data['observacao'] ?? null,
                        );

                        Notification::make()
                            ->title('Pagamento registrado')
                            ->body('Próximo vencimento: '.$record->fresh()->proximo_vencimento->format('d/m/Y'))
                            ->success()
                            ->send();
                    }),

                Action::make('historico')
                    ->label('Histórico')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->modalHeading(fn (Tenant $record) => "Pagamentos — {$record->nome}")
                    ->modalContent(fn (Tenant $record) => view('filament.super-admin.pages.pagamentos-historico', [
                        'pagamentos' => $record->pagamentos()->latest('pago_em')->get(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar'),
            ])
            ->striped()
            ->paginated([25, 50, 100]);
    }
}
