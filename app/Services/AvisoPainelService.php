<?php

namespace App\Services;

use App\Jobs\EnviarPushJob;
use App\Models\Agendamento;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Avisos no sininho do painel — canal que não depende de WhatsApp.
 *
 * Vai para o profissional do agendamento (quando ele tem login) e para os
 * donos/admins do estabelecimento. Quem executou a ação não recebe aviso:
 * o dono que acabou de marcar pela agenda já sabe que marcou.
 */
class AvisoPainelService
{
    public function novoAgendamento(Agendamento $agendamento): void
    {
        $this->enviar(
            $agendamento,
            titulo: 'Novo agendamento',
            corpo: sprintf(
                '%s — %s às %s (%s)',
                $agendamento->cliente_nome,
                $agendamento->data_hora->format('d/m'),
                $agendamento->data_hora->format('H:i'),
                $agendamento->nomesServicos(),
            ),
            icone: 'heroicon-o-calendar-days',
            cor: 'success',
        );
    }

    public function agendamentoCancelado(Agendamento $agendamento): void
    {
        $this->enviar(
            $agendamento,
            titulo: 'Agendamento cancelado',
            corpo: sprintf(
                '%s — %s às %s. O horário foi liberado.',
                $agendamento->cliente_nome,
                $agendamento->data_hora->format('d/m'),
                $agendamento->data_hora->format('H:i'),
            ),
            icone: 'heroicon-o-x-circle',
            cor: 'danger',
        );
    }

    public function agendamentoRemarcado(Agendamento $agendamento): void
    {
        $this->enviar(
            $agendamento,
            titulo: 'Agendamento remarcado',
            corpo: sprintf(
                '%s — agora %s às %s',
                $agendamento->cliente_nome,
                $agendamento->data_hora->format('d/m'),
                $agendamento->data_hora->format('H:i'),
            ),
            icone: 'heroicon-o-arrow-path',
            cor: 'warning',
        );
    }

    private function enviar(
        Agendamento $agendamento,
        string $titulo,
        string $corpo,
        string $icone,
        string $cor,
    ): void {
        $destinatarios = $this->destinatarios($agendamento);

        if ($destinatarios->isEmpty()) {
            return;
        }

        $url = route('filament.admin.pages.agenda-geral');

        $notificacao = Notification::make()
            ->title($titulo)
            ->body($corpo)
            ->icon($icone)
            ->color($cor)
            ->actions([
                Action::make('ver')
                    ->label('Ver na agenda')
                    ->url($url)
                    ->markAsRead(),
            ]);

        foreach ($destinatarios as $user) {
            // Sininho: sempre. Push: só pra quem autorizou algum aparelho.
            $notificacao->sendToDatabase($user);

            EnviarPushJob::dispatch($user->id, [
                'title' => $titulo,
                'body' => $corpo,
                'url' => $url,
            ]);
        }
    }

    /** @return Collection<int, User> */
    private function destinatarios(Agendamento $agendamento): Collection
    {
        if (! $agendamento->tenant_id) {
            return collect();
        }

        return User::withoutGlobalScopes()
            ->where('tenant_id', $agendamento->tenant_id)
            ->where(function ($q) use ($agendamento) {
                // Dono do estabelecimento
                $q->where('role', 'admin');
                // ...e o profissional daquele atendimento, se tiver login
                if ($agendamento->profissional_id) {
                    $q->orWhere('profissional_id', $agendamento->profissional_id);
                }
            })
            ->get()
            // Quem fez a ação não precisa ser avisado do que acabou de fazer
            ->reject(fn (User $u) => auth('admin')->check() && $u->id === auth('admin')->id())
            ->values();
    }
}
