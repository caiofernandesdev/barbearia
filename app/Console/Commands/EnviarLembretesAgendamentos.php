<?php

namespace App\Console\Commands;

use App\Models\Agendamento;
use App\Models\ConfiguracaoBarbearia;
use App\Observers\AgendamentoObserver;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;

class EnviarLembretesAgendamentos extends Command
{
    protected $signature   = 'agendamentos:lembretes';
    protected $description = 'Envia lembrete por WhatsApp para agendamentos pendentes de amanhã';

    public function __construct(private WhatsAppService $whatsapp)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $config        = ConfiguracaoBarbearia::getInstance();
        $nomeBarbearia = $config->nome_barbearia;
        $diasAntes     = $config->dias_antecedencia_lembrete ?? 1;

        $dataAlvo = now()->addDays($diasAntes);

        $agendamentos = Agendamento::whereIn('status', ['pendente', 'confirmado'])
            ->whereDate('data_hora', $dataAlvo->toDateString())
            ->with(['profissional', 'servico'])
            ->get();

        foreach ($agendamentos as $ag) {
            // Pendente: pede confirmação via WhatsApp; Confirmado: só lembra o horário
            $mensagem = $ag->status === 'confirmado'
                ? AgendamentoObserver::mensagemLembreteConfirmado($ag, $nomeBarbearia)
                : AgendamentoObserver::mensagemLembrete($ag, $nomeBarbearia);

            $this->whatsapp->enviarTexto($ag->cliente_telefone, $mensagem);
        }

        $this->info("Lembretes enviados: {$agendamentos->count()}");

        return self::SUCCESS;
    }
}