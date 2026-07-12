<?php

namespace App\Console\Commands;

use App\Models\Mensalista;
use App\Models\Tenant;
use App\Services\GerarAgendamentosFixosService;
use Illuminate\Console\Command;

class GerarAgendamentosFixos extends Command
{
    protected $signature = 'agendamentos:gerar-fixos {--semanas=8}';

    protected $description = 'Mantém a agenda dos mensalistas fixos populada gerando os agendamentos das próximas semanas';

    public function handle(GerarAgendamentosFixosService $service): int
    {
        $semanas = (int) $this->option('semanas');
        $total = 0;

        foreach (Tenant::where('ativo', true)->get() as $tenant) {
            app()->instance('current_tenant', $tenant);

            $mensalistas = Mensalista::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('tipo', 'mensalista_fixo')
                ->get();

            foreach ($mensalistas as $m) {
                $total += $service->gerar($m, $semanas);
            }
        }

        $this->info("Agendamentos fixos gerados: {$total}");

        return self::SUCCESS;
    }
}
