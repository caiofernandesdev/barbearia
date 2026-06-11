<?php

namespace Database\Seeders;

use App\Models\Agendamento;
use App\Models\ConfiguracaoBarbearia;
use App\Models\Mensalista;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // ─── Admin ────────────────────────────────────────────────────────────
        User::firstOrCreate(
            ['email' => 'admin@barbearia.com'],
            ['name' => 'Administrador', 'password' => Hash::make('password')]
        );

        // ─── Configuração da barbearia ────────────────────────────────────────
        ConfiguracaoBarbearia::firstOrCreate([], [
            'nome_barbearia'                 => 'Barbearia Studio',
            'dias_funcionamento'             => [1, 2, 3, 4, 5, 6],
            'horario_abertura'               => '08:00',
            'horario_encerramento'           => '19:00',
            'intervalo_minutos'              => 60,
            'mensalista_limite_cortes_semana' => 1,
        ]);

        // ─── Serviços ─────────────────────────────────────────────────────────
        $servicos = [
            ['nome' => 'Corte Simples',   'preco' => 35.00, 'duracao_minutos' => 30, 'ordem' => 1, 'destaque' => false],
            ['nome' => 'Corte Degradê',   'preco' => 45.00, 'duracao_minutos' => 45, 'ordem' => 2, 'destaque' => true],
            ['nome' => 'Corte + Barba',   'preco' => 65.00, 'duracao_minutos' => 60, 'ordem' => 3, 'destaque' => true],
            ['nome' => 'Barba',           'preco' => 35.00, 'duracao_minutos' => 30, 'ordem' => 4, 'destaque' => false],
            ['nome' => 'Sobrancelha',     'preco' => 15.00, 'duracao_minutos' => 15, 'ordem' => 5, 'destaque' => false],
            ['nome' => 'Pigmentação',     'preco' => 80.00, 'duracao_minutos' => 60, 'ordem' => 6, 'destaque' => false],
        ];
        foreach ($servicos as $s) {
            Servico::firstOrCreate(['nome' => $s['nome']], $s);
        }
        $servicoIds = Servico::pluck('id')->toArray();

        // ─── Profissionais ────────────────────────────────────────────────────
        $profissionaisData = [
            ['nome' => 'Lucas Mendes',      'comissao_percentual' => 40, 'limite_mensalistas' => 20],
            ['nome' => 'Rafael Santos',     'comissao_percentual' => 40, 'limite_mensalistas' => 15],
            ['nome' => 'Matheus Oliveira',  'comissao_percentual' => 35, 'limite_mensalistas' => 10],
        ];
        foreach ($profissionaisData as $p) {
            Profissional::firstOrCreate(['nome' => $p['nome']], array_merge($p, ['ativo' => true]));
        }
        $profissionais = Profissional::where('ativo', true)->get();

        // ─── Mensalistas ──────────────────────────────────────────────────────
        $mensalistasData = [
            ['nome' => 'Carlos Eduardo',  'telefone' => '51999990001', 'tipo' => 'mensalista',       'valor_mensalidade' => 100.00, 'limite_cortes_semana' => 1],
            ['nome' => 'Thiago Rocha',    'telefone' => '51999990002', 'tipo' => 'mensalista',       'valor_mensalidade' => 100.00, 'limite_cortes_semana' => 1],
            ['nome' => 'Felipe Martins',  'telefone' => '51999990003', 'tipo' => 'mensalista',       'valor_mensalidade' => 120.00, 'limite_cortes_semana' => 2],
            ['nome' => 'Bruno Alves',     'telefone' => '51999990004', 'tipo' => 'mensalista',       'valor_mensalidade' => 100.00, 'limite_cortes_semana' => 1],
            ['nome' => 'Diego Lima',      'telefone' => '51999990005', 'tipo' => 'mensalista',       'valor_mensalidade' => 100.00, 'limite_cortes_semana' => 1],
            ['nome' => 'Vinícius Costa',  'telefone' => '51999990006', 'tipo' => 'mensalista_fixo',  'valor_mensalidade' => 150.00, 'limite_cortes_semana' => 1],
            ['nome' => 'André Ferreira',  'telefone' => '51999990007', 'tipo' => 'mensalista_fixo',  'valor_mensalidade' => 150.00, 'limite_cortes_semana' => 1],
            ['nome' => 'Pedro Nunes',     'telefone' => '51999990008', 'tipo' => 'mensalista',       'valor_mensalidade' => 100.00, 'limite_cortes_semana' => 1],
            ['nome' => 'Gabriel Torres',  'telefone' => '51999990009', 'tipo' => 'mensalista',       'valor_mensalidade' => 100.00, 'limite_cortes_semana' => 1],
            ['nome' => 'Rodrigo Pinto',   'telefone' => '51999990010', 'tipo' => 'mensalista',       'valor_mensalidade' => 100.00, 'limite_cortes_semana' => 1],
        ];
        foreach ($mensalistasData as $m) {
            Mensalista::firstOrCreate(['telefone' => $m['telefone']], $m);
        }
        $mensalistas = Mensalista::whereIn('tipo', ['mensalista', 'mensalista_fixo'])->get();

        // ─── Agendamentos (últimos 3 meses) ───────────────────────────────────
        $clientesAvulsos = [
            ['nome' => 'João Silva',      'telefone' => '51988881001'],
            ['nome' => 'Marcos Dias',     'telefone' => '51988881002'],
            ['nome' => 'Paulo Souza',     'telefone' => '51988881003'],
            ['nome' => 'Ricardo Lima',    'telefone' => '51988881004'],
            ['nome' => 'Eduardo Cunha',   'telefone' => '51988881005'],
            ['nome' => 'Leandro Costa',   'telefone' => '51988881006'],
            ['nome' => 'Fernando Ramos',  'telefone' => '51988881007'],
            ['nome' => 'Leonardo Melo',   'telefone' => '51988881008'],
        ];

        $horarios = ['08:00', '09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00'];

        // Gera ~120 agendamentos nos últimos 3 meses
        $usedSlots = [];

        for ($daysAgo = 90; $daysAgo >= 1; $daysAgo--) {
            $data = now()->subDays($daysAgo);

            // Pula domingos
            if ($data->dayOfWeek === 0) continue;

            // 3-5 agendamentos por dia
            $qtdDia = rand(3, 5);
            shuffle($horarios);
            $horariosUsados = array_slice($horarios, 0, $qtdDia);

            foreach ($horariosUsados as $hora) {
                $profissional = $profissionais->random();
                $slotKey = $profissional->id . '-' . $data->format('Y-m-d') . '-' . $hora;
                if (isset($usedSlots[$slotKey])) continue;
                $usedSlots[$slotKey] = true;

                $servicoId = $servicoIds[array_rand($servicoIds)];
                $isMensalista = rand(1, 4) === 1; // 25% chance de ser mensalista

                if ($isMensalista && $mensalistas->count()) {
                    $mensalista = $mensalistas->random();
                    $clienteNome = $mensalista->nome;
                    $clienteTel  = $mensalista->telefone;
                    $mensalistaId = $mensalista->id;
                    $mensalistaBool = true;
                } else {
                    $avulso = $clientesAvulsos[array_rand($clientesAvulsos)];
                    $clienteNome = $avulso['nome'];
                    $clienteTel  = $avulso['telefone'];
                    $mensalistaId = null;
                    $mensalistaBool = false;
                }

                // Status: passado = maioria concluido, alguns cancelados
                $rand = rand(1, 10);
                $status = $rand <= 7 ? 'concluido' : ($rand <= 9 ? 'confirmado' : 'cancelado');

                Agendamento::create([
                    'cliente_nome'      => $clienteNome,
                    'cliente_telefone'  => $clienteTel,
                    'profissional_id'   => $profissional->id,
                    'servico_id'        => $servicoId,
                    'data_hora'         => $data->format('Y-m-d') . ' ' . $hora . ':00',
                    'status'            => $status,
                    'mensalista'        => $mensalistaBool,
                    'mensalista_id'     => $mensalistaId,
                ]);
            }
        }

        // Agendamentos futuros (próximos 7 dias) — pendentes e confirmados
        for ($daysAhead = 1; $daysAhead <= 7; $daysAhead++) {
            $data = now()->addDays($daysAhead);
            if ($data->dayOfWeek === 0) continue;

            $qtdDia = rand(2, 4);
            shuffle($horarios);
            $horariosUsados = array_slice($horarios, 0, $qtdDia);

            foreach ($horariosUsados as $hora) {
                $profissional = $profissionais->random();
                $slotKey = $profissional->id . '-' . $data->format('Y-m-d') . '-' . $hora;
                if (isset($usedSlots[$slotKey])) continue;
                $usedSlots[$slotKey] = true;

                $avulso = $clientesAvulsos[array_rand($clientesAvulsos)];
                Agendamento::create([
                    'cliente_nome'     => $avulso['nome'],
                    'cliente_telefone' => $avulso['telefone'],
                    'profissional_id'  => $profissional->id,
                    'servico_id'       => $servicoIds[array_rand($servicoIds)],
                    'data_hora'        => $data->format('Y-m-d') . ' ' . $hora . ':00',
                    'status'           => rand(0, 1) ? 'pendente' : 'confirmado',
                    'mensalista'       => false,
                ]);
            }
        }
    }
}
