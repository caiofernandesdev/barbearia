# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# First-time setup (installs deps, generates key, migrates, builds assets)
composer run setup

# Run all services simultaneously (server + queue + logs + Vite HMR)
composer run dev

# Run tests
composer run test

# Run a single test file
php artisan test --filter NomeDoTeste

# Code formatting (Laravel Pint)
./vendor/bin/pint

# Create the public/storage symlink (required for professional photos to appear)
php artisan storage:link
```

## Architecture

This is a barbershop appointment booking system with two distinct surfaces:

**Public surface** (`routes/web.php`) — chatbot-style multi-step booking flow
- Single controller: `AgendamentoController`
- All business rules live in this controller and must be documented with inline comments
- The booking wizard is entirely client-side state (vanilla JS `estado` machine in `index.blade.php`)
- Steps: nome → telefone → profissional → serviço → data → hora → confirmar

**Admin panel** (`/admin`) — Filament 4 panel requiring authentication
- Resources organized under `app/Filament/Resources/{Entity}/`
- Each resource splits concerns: `Schemas/` for forms, `Tables/` for table definitions, `Pages/` for CRUD pages

## UI Rules — Filament Admin Panel

**Never use custom HTML/Blade tables inside Filament pages.** Tailwind is compiled only from Filament's own source; arbitrary classes in custom Blade won't be in the compiled CSS and will render broken. Always use:
- `InteractsWithTable` + `TextColumn` for any tabular data
- `Stat::make()` inside a named `Schema` for summary cards
- `<x-filament::section>` for containers/sections
- `<x-filament-panels::page>` as the page root

**Standalone Livewire components with Filament tables** (when a page needs more than one table) must implement all three contracts and use all three traits:
```php
implements HasActions, HasSchemas, HasTable
use InteractsWithActions, InteractsWithSchemas, InteractsWithTable;
```
Omitting any of these causes runtime errors (`cacheSchema`, `$mountedActions`, etc.).

**Filament 4 closure parameter injection** — closures passed to `getStateUsing()`, `->color()`, `->formatStateUsing()`, etc. receive arguments **by name**, not by position. Always use `$record` (not `$r` or any alias) to receive the Eloquent model in column closures. Using the wrong name injects `null`.

**`->statePath('data')` on named schemas** — do NOT use `->statePath('data')` with a shared `public array $data = []` property for reactive Filament page filters. It causes unreliable Livewire re-render sync. Instead, declare individual public Livewire properties (`public string $filtroMes`, etc.) and bind fields directly to them without `->statePath()`.

## Key Patterns

**Filament 4 API** (not v3) — forms use `Filament\Schemas\Schema`, tables use `Filament\Tables\Table`. Do not use v3 patterns.

**Booking slot availability** — logic lives in `app/Services/DisponibilidadeService.php`, called by `AgendamentoController::horariosDisponiveis`. Two modes:
- **Lista** (profissional has `horarios_trabalho`): pre-defined times filtered by overlap — barbearia backward-compat.
- **Gap-based** (no `horarios_trabalho`): finds free gaps between merged busy intervals, generates slots within each gap stepping by `intervalo_minutos`; a slot is valid only when `slot_start + duracao ≤ gap_end`.

Overlap detection uses: `slotStart < existingEnd && slotEnd > existingStart`.
Window: today → today+14 days. Buffer: 30 min for same-day slots.

**Professional photos** — stored by Filament's `FileUpload` in `storage/app/public/profissionais/` (relative path saved in DB). The API returns the full public URL via `asset('storage/' . $p->foto)`. Requires `php artisan storage:link` once.

**Phone-based identity** — there are no user accounts on the public side. Clients are identified solely by phone number (digits only, stripped of formatting). A client may not hold more than one active booking at a time.

**Date handling** — `data_hora` is stored as a full datetime. The booking API (`/api/horarios-disponiveis`) accepts `data` (Y-m-d) + `servico_id` + `profissional_id` and returns `[{ hora: "H:i", datetime: "Y-m-d H:i" }]`. The frontend composes the final `data_hora` from the selected slot's `datetime`.

## Stack

- Laravel 13 / PHP 8.3
- Filament 4 (admin panel, primary color: Amber, path: `/admin`)
- Tailwind CSS 4 via CDN in public views (not compiled); compiled via Vite only for Filament
- SQLite by default (`.env` `DB_CONNECTION=sqlite`)
- No Livewire in the public booking flow — vanilla JS only

## Infraestrutura real (conferida em 08/11/2026)

O `DEPLOY.md`/`OPERACAO.md` descrevem a instalação original; o estado real é este:

- VPS Hostinger `srv1814528` (`179.197.66.135`), **Ubuntu 24.04** (não 22.04),
  7,8 GB RAM, 2 vCPU, 96 GB de disco (~7% usado), **sem swap**
- PHP 8.3.32, MySQL 8.0.46, Nginx, Supervisor (`barbearia-worker` x2), Evolution API
  em Docker (`/opt/evolution-api`, 3 containers)
- Site `default` do Nginx ainda habilitado (inofensivo)
- **Backup do banco não existia** apesar do `OPERACAO.md` dizer que sim. Configurado
  em 08/11/2026: cron do root às 3h30 → `/var/backups/barbearia/`, guarda 14 dias.
  Fotos (`storage/app/public`) não entram no backup.
- Agendador (`schedule:run` no crontab do `www-data`) **não foi conferido** — checar
  com `crontab -l -u www-data`; sem ele, lembretes de WhatsApp das 9h não saem.
- Painéis Filament não têm "esqueci a senha"; o Atendix não depende de SMTP.

### A VPS também hospeda o Sobrou (projeto pessoal do Gabriel)

- `/var/www/sobrou`, banco `sobrou`, Nginx `sites-available/sobrou`, domínio `sobrou.cc`
- **Não mexer** nesses itens ao trabalhar no Atendix
- `systemctl reload php8.3-fpm` recarrega o PHP dos dois sistemas (não derruba nada)
- `php artisan down/up` vale só para a pasta onde é rodado
