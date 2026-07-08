<?php

namespace App\Providers;

use App\Models\Agendamento;
use App\Models\Tenant;
use App\Observers\AgendamentoObserver;
use Filament\Events\ServingFilament;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Agendamento::observe(AgendamentoObserver::class);


        Event::listen(ServingFilament::class, function () {
            if (app()->bound('current_tenant')) return;

            $panel = filament()->getCurrentPanel();
            if ($panel && $panel->getId() === 'super-admin') return;

            try {
                $user = auth('admin')->user();
                if ($user && $user->tenant_id) {
                    $tenant = Tenant::withoutGlobalScopes()->find($user->tenant_id);
                    if ($tenant && $tenant->ativo) {
                        app()->instance('current_tenant', $tenant);
                        auth()->shouldUse('admin');
                    } elseif ($user) {
                        auth('admin')->logout();
                    }
                }
            } catch (\Throwable) {}
        });
    }
}
