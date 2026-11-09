<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;
use Illuminate\Support\Facades\Cookie;
use Livewire\Attributes\Url;

/**
 * Alterna a listagem entre "lista" (tabela) e "grade" (cards).
 *
 * A página usa este trait para expor a propriedade de layout + o botão de
 * alternância; a *Table* do recurso lê {@see emGrade()} via
 * `$table->getLivewire()->emGrade()` para escolher as colunas.
 *
 * Memória da escolha:
 * - `#[Url]`: o layout aparece no link (refresh e link compartilhado mantêm).
 * - Cookie por página (1 ano): ao voltar para a listagem sem `?layoutView=` na
 *   URL, abre no último layout escolhido. É por navegador de propósito: no
 *   celular a pessoa costuma preferir grade e no computador, lista.
 */
trait TogglesTableLayout
{
    #[Url]
    public string $layoutView = 'lista';

    /** Livewire chama automaticamente no mount da página (hook mount{NomeDoTrait}). */
    public function mountTogglesTableLayout(): void
    {
        // Layout na URL tem prioridade; o cookie só vale quando ela não diz nada.
        if (request()->query('layoutView') !== null) {
            return;
        }

        $salvo = request()->cookie($this->layoutCookieName());
        if (in_array($salvo, ['lista', 'grade'], true)) {
            $this->layoutView = $salvo;
        }
    }

    public function emGrade(): bool
    {
        return $this->layoutView === 'grade';
    }

    protected function layoutToggleAction(): Action
    {
        return Action::make('toggleLayout')
            ->label(fn () => $this->emGrade() ? 'Ver em lista' : 'Ver em grade')
            ->icon(fn () => $this->emGrade() ? 'heroicon-o-bars-3' : 'heroicon-o-squares-2x2')
            ->color('gray')
            ->action(function () {
                $this->layoutView = $this->emGrade() ? 'lista' : 'grade';
                Cookie::queue($this->layoutCookieName(), $this->layoutView, 60 * 24 * 365);
                // Reconstrói a tabela nesta mesma request com as novas colunas.
                $this->resetTable();
            });
    }

    /** Um cookie por listagem: escolher grade em Serviços não muda Profissionais. */
    protected function layoutCookieName(): string
    {
        return 'layout_'.class_basename(static::class);
    }
}
