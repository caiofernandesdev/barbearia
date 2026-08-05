<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;
use Livewire\Attributes\Url;

/**
 * Alterna a listagem entre "lista" (tabela) e "grade" (cards).
 *
 * A página usa este trait para expor a propriedade de layout + o botão de
 * alternância; a *Table* do recurso lê {@see emGrade()} via
 * `$table->getLivewire()->emGrade()` para escolher as colunas.
 *
 * A propriedade é `#[Url]` para o layout escolhido sobreviver a refresh e
 * ser compartilhável por link.
 */
trait TogglesTableLayout
{
    #[Url]
    public string $layoutView = 'lista';

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
                // Reconstrói a tabela nesta mesma request com as novas colunas.
                $this->resetTable();
            });
    }
}
