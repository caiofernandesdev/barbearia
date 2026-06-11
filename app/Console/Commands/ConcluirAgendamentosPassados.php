<?php

namespace App\Console\Commands;

use App\Models\Agendamento;
use Illuminate\Console\Command;

class ConcluirAgendamentosPassados extends Command
{
    protected $signature   = 'agendamentos:concluir';
    protected $description = 'Marca como concluído agendamentos cujo horário já passou';

    public function handle(): int
    {
        $total = Agendamento::whereIn('status', ['pendente', 'confirmado'])
            ->where('data_hora', '<', now())
            ->update(['status' => 'concluido']);

        $this->info("Agendamentos concluídos: {$total}");

        return self::SUCCESS;
    }
}