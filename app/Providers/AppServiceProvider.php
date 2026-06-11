<?php

namespace App\Providers;

use App\Models\Agendamento;
use App\Observers\AgendamentoObserver;
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
    }
}
