<?php

namespace App\Filament\Pages;

use App\Models\Profissional;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Agenda (dono) — mesma visão do "Meu Painel" do profissional, mas com um
 * seletor que permite o admin ver a agenda de qualquer profissional.
 */
class AgendaGeral extends Page
{
    protected string $view = 'filament.pages.agenda-geral';

    protected static ?string $navigationLabel = 'Agenda';

    protected static ?string $title = 'Agenda';

    protected static \BackedEnum|string|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 0;

    public static function getNavigationGroup(): string
    {
        return 'Agenda';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public ?int $profissionalId = null;

    public function mount(): void
    {
        // Abre já no primeiro profissional ativo
        $this->profissionalId = Profissional::where('ativo', true)->orderBy('nome')->value('id');
    }

    public function getProfissionaisProperty(): array
    {
        return Profissional::where('ativo', true)->orderBy('nome')->pluck('nome', 'id')->toArray();
    }

    public function getNomeProfissionalProperty(): string
    {
        return $this->profissionais[$this->profissionalId] ?? 'Profissional';
    }
}
