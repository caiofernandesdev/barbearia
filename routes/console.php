<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Conclui automaticamente agendamentos cujo horário já passou
Schedule::command('agendamentos:concluir')->hourly();

// Envia lembrete D-1 para agendamentos pendentes de amanhã
Schedule::command('agendamentos:lembretes')->dailyAt('09:00');

// Cancela automaticamente pendentes não confirmados dentro do prazo
Schedule::command('agendamentos:cancelar-nao-confirmados')->everyFifteenMinutes();
