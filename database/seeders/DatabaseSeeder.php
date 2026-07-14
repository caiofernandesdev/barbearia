<?php

namespace Database\Seeders;

use App\Models\Agendamento;
use App\Models\ConfiguracaoBarbearia;
use App\Models\Mensalista;
use App\Models\Plano;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\Tenant;
use App\Models\TipoEstabelecimento;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // ─── Tipos de Estabelecimento ─────────────────────────────────────────
        $tipoBarbearia = TipoEstabelecimento::firstOrCreate(['nome' => 'Barbearia'], ['icone' => '💈', 'ativo' => true]);
        TipoEstabelecimento::firstOrCreate(['nome' => 'Salão de Beleza'], ['icone' => '💇', 'ativo' => true]);
        TipoEstabelecimento::firstOrCreate(['nome' => 'Clínica de Estética'], ['icone' => '🏥', 'ativo' => true]);
        TipoEstabelecimento::firstOrCreate(['nome' => 'Pet Shop'], ['icone' => '🐾', 'ativo' => true]);
        TipoEstabelecimento::firstOrCreate(['nome' => 'Studio de Tatuagem'], ['icone' => '🎨', 'ativo' => true]);

        // ─── Planos ───────────────────────────────────────────────────────────
        Plano::firstOrCreate(['nome' => 'Básico'], [
            'descricao' => 'Agendamento online + painel admin básico',
            'preco_mensal' => 97.00,
            'features' => ['mensalistas', 'indisponibilidades'],
            'max_profissionais' => 2,
            'max_usuarios' => 3,
            'ativo' => true,
        ]);

        $planoPro = Plano::firstOrCreate(['nome' => 'Pro'], [
            'descricao' => 'Tudo do Básico + WhatsApp + Relatórios + Salário Emocional',
            'preco_mensal' => 197.00,
            // Relatórios granulares (rel_*): Pro tem os principais
            'features' => ['mensalistas', 'indisponibilidades', 'relatorios', 'salario_emocional', 'whatsapp', 'agenda_fixa', 'lista_espera',
                'rel_atendimentos', 'rel_receita', 'rel_clientes_unicos', 'rel_cancelamentos',
                'rel_servico_top', 'rel_desempenho_barbeiro', 'rel_agendamentos_periodo'],
            'max_profissionais' => 8,
            'max_usuarios' => 10,
            'ativo' => true,
        ]);

        Plano::firstOrCreate(['nome' => 'Enterprise'], [
            'descricao' => 'Acesso completo a todos os módulos',
            'preco_mensal' => 397.00,
            // Enterprise: todos os relatórios (inclui evolução mensal)
            'features' => array_merge(
                ['mensalistas', 'indisponibilidades', 'relatorios', 'repescagem', 'salario_emocional', 'whatsapp', 'campos_agendamento', 'agenda_fixa', 'lista_espera', 'import_export'],
                array_keys(Plano::RELATORIOS)
            ),
            'ativo' => true,
        ]);

        // ─── Tenant demo ──────────────────────────────────────────────────────
        $tenant = Tenant::firstOrCreate(['slug' => 'demo'], [
            'nome' => 'Barbearia Studio Demo',
            'tipo' => 'barbearia',
            'tipo_estabelecimento_id' => $tipoBarbearia->id,
            'plano_id' => $planoPro->id,
            'ativo' => true,
        ]);
        $tid = $tenant->id;

        // ─── Usuários ─────────────────────────────────────────────────────────
        // WithoutModelEvents suprime o hook do BelongsToTenant — passamos tenant_id explicitamente

        User::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'super@saas.com'],
            ['name' => 'Super Admin', 'password' => Hash::make('password'), 'role' => 'super_admin', 'tenant_id' => null]
        );

        User::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'admin@barbearia.com'],
            ['name' => 'Administrador', 'password' => Hash::make('password'), 'role' => 'admin', 'tenant_id' => $tid]
        );

        // ─── Configuração ─────────────────────────────────────────────────────
        ConfiguracaoBarbearia::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tid],
            [
                'nome_barbearia' => 'Barbearia Studio',
                'dias_funcionamento' => [1, 2, 3, 4, 5, 6],
                'horario_abertura' => '08:00',
                'horario_encerramento' => '19:00',
                'intervalo_minutos' => 60,
                'mensalista_limite_cortes_semana' => 1,
                'tenant_id' => $tid,
            ]
        );

        // ─── Serviços ─────────────────────────────────────────────────────────
        $servicosData = [
            ['nome' => 'Corte Simples', 'preco' => 35.00, 'duracao_minutos' => 30, 'ordem' => 1, 'destaque' => false],
            ['nome' => 'Corte Degradê', 'preco' => 45.00, 'duracao_minutos' => 45, 'ordem' => 2, 'destaque' => true],
            ['nome' => 'Corte + Barba', 'preco' => 65.00, 'duracao_minutos' => 60, 'ordem' => 3, 'destaque' => true],
            ['nome' => 'Barba',         'preco' => 35.00, 'duracao_minutos' => 30, 'ordem' => 4, 'destaque' => false],
            ['nome' => 'Sobrancelha',   'preco' => 15.00, 'duracao_minutos' => 15, 'ordem' => 5, 'destaque' => false],
            ['nome' => 'Pigmentação',   'preco' => 80.00, 'duracao_minutos' => 60, 'ordem' => 6, 'destaque' => false],
        ];
        foreach ($servicosData as $s) {
            Servico::withoutGlobalScopes()->firstOrCreate(
                ['nome' => $s['nome'], 'tenant_id' => $tid],
                array_merge($s, ['tenant_id' => $tid])
            );
        }
        $servicoIds = Servico::withoutGlobalScopes()->where('tenant_id', $tid)->pluck('id')->toArray();

        // ─── Profissionais ────────────────────────────────────────────────────
        $profissionaisData = [
            ['nome' => 'Lucas Mendes',     'comissao_percentual' => 40, 'limite_mensalistas' => 20],
            ['nome' => 'Rafael Santos',    'comissao_percentual' => 40, 'limite_mensalistas' => 15],
            ['nome' => 'Matheus Oliveira', 'comissao_percentual' => 35, 'limite_mensalistas' => 10],
        ];
        foreach ($profissionaisData as $p) {
            Profissional::withoutGlobalScopes()->firstOrCreate(
                ['nome' => $p['nome'], 'tenant_id' => $tid],
                array_merge($p, ['ativo' => true, 'tenant_id' => $tid])
            );
        }
        $profissionais = Profissional::withoutGlobalScopes()->where('tenant_id', $tid)->where('ativo', true)->get();

        // ─── Mensalistas ──────────────────────────────────────────────────────
        $mensalistasData = [
            ['nome' => 'Carlos Eduardo', 'telefone' => '51999990001', 'tipo' => 'mensalista',      'valor_mensalidade' => 100.00, 'limite_cortes_semana' => 1],
            ['nome' => 'Thiago Rocha',   'telefone' => '51999990002', 'tipo' => 'mensalista',      'valor_mensalidade' => 100.00, 'limite_cortes_semana' => 1],
            ['nome' => 'Felipe Martins', 'telefone' => '51999990003', 'tipo' => 'mensalista',      'valor_mensalidade' => 120.00, 'limite_cortes_semana' => 2],
            ['nome' => 'Bruno Alves',    'telefone' => '51999990004', 'tipo' => 'mensalista',      'valor_mensalidade' => 100.00, 'limite_cortes_semana' => 1],
            ['nome' => 'Diego Lima',     'telefone' => '51999990005', 'tipo' => 'mensalista',      'valor_mensalidade' => 100.00, 'limite_cortes_semana' => 1],
            ['nome' => 'Vinícius Costa', 'telefone' => '51999990006', 'tipo' => 'mensalista_fixo', 'valor_mensalidade' => 150.00, 'limite_cortes_semana' => 1],
            ['nome' => 'André Ferreira', 'telefone' => '51999990007', 'tipo' => 'mensalista_fixo', 'valor_mensalidade' => 150.00, 'limite_cortes_semana' => 1],
            ['nome' => 'Pedro Nunes',    'telefone' => '51999990008', 'tipo' => 'mensalista',      'valor_mensalidade' => 100.00, 'limite_cortes_semana' => 1],
            ['nome' => 'Gabriel Torres', 'telefone' => '51999990009', 'tipo' => 'mensalista',      'valor_mensalidade' => 100.00, 'limite_cortes_semana' => 1],
            ['nome' => 'Rodrigo Pinto',  'telefone' => '51999990010', 'tipo' => 'mensalista',      'valor_mensalidade' => 100.00, 'limite_cortes_semana' => 1],
        ];
        foreach ($mensalistasData as $m) {
            Mensalista::withoutGlobalScopes()->firstOrCreate(
                ['telefone' => $m['telefone'], 'tenant_id' => $tid],
                array_merge($m, ['tenant_id' => $tid])
            );
        }
        $mensalistas = Mensalista::withoutGlobalScopes()
            ->where('tenant_id', $tid)
            ->whereIn('tipo', ['mensalista', 'mensalista_fixo'])
            ->get();

        // ─── Agendamentos ─────────────────────────────────────────────────────
        $clientesAvulsos = [
            ['nome' => 'João Silva',     'telefone' => '51988881001'],
            ['nome' => 'Marcos Dias',    'telefone' => '51988881002'],
            ['nome' => 'Paulo Souza',    'telefone' => '51988881003'],
            ['nome' => 'Ricardo Lima',   'telefone' => '51988881004'],
            ['nome' => 'Eduardo Cunha',  'telefone' => '51988881005'],
            ['nome' => 'Leandro Costa',  'telefone' => '51988881006'],
            ['nome' => 'Fernando Ramos', 'telefone' => '51988881007'],
            ['nome' => 'Leonardo Melo',  'telefone' => '51988881008'],
        ];

        $clientesRepescagem = [
            ['nome' => 'Alexandre Gomes',    'telefone' => '51977770001', 'diasAtras' => 32],
            ['nome' => 'Renato Carvalho',    'telefone' => '51977770002', 'diasAtras' => 45],
            ['nome' => 'Henrique Fonseca',   'telefone' => '51977770003', 'diasAtras' => 50],
            ['nome' => 'Marcelo Teixeira',   'telefone' => '51977770004', 'diasAtras' => 60],
            ['nome' => 'Gustavo Pereira',    'telefone' => '51977770005', 'diasAtras' => 72],
            ['nome' => 'Sandro Vasconcelos', 'telefone' => '51977770006', 'diasAtras' => 89],
            ['nome' => 'Flávio Barbosa',     'telefone' => '51977770007', 'diasAtras' => 35],
            ['nome' => 'Cristiano Moraes',   'telefone' => '51977770008', 'diasAtras' => 55],
        ];

        $horarios = ['08:00', '09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00'];
        $usedSlots = [];

        // Histórico de 3 meses
        for ($daysAgo = 90; $daysAgo >= 1; $daysAgo--) {
            $data = now()->subDays($daysAgo);
            if ($data->dayOfWeek === 0) {
                continue;
            }

            $qtdDia = rand(3, 5);
            shuffle($horarios);
            $horariosUsados = array_slice($horarios, 0, $qtdDia);

            foreach ($horariosUsados as $hora) {
                $profissional = $profissionais->random();
                $slotKey = $profissional->id.'-'.$data->format('Y-m-d').'-'.$hora;
                if (isset($usedSlots[$slotKey])) {
                    continue;
                }
                $usedSlots[$slotKey] = true;

                $servicoId = $servicoIds[array_rand($servicoIds)];
                $isMensalista = rand(1, 4) === 1;

                if ($isMensalista) {
                    $m = $mensalistas->random();
                    $clienteNome = $m->nome;
                    $clienteTel = $m->telefone;
                    $mensalistaId = $m->id;
                    $ehMensalista = true;
                } else {
                    $av = $clientesAvulsos[array_rand($clientesAvulsos)];
                    $clienteNome = $av['nome'];
                    $clienteTel = $av['telefone'];
                    $mensalistaId = null;
                    $ehMensalista = false;
                }

                $r = rand(1, 10);
                $status = $r <= 7 ? 'concluido' : ($r <= 9 ? 'confirmado' : 'cancelado');

                Agendamento::create([
                    'tenant_id' => $tid,
                    'cliente_nome' => $clienteNome,
                    'cliente_telefone' => $clienteTel,
                    'profissional_id' => $profissional->id,
                    'servico_id' => $servicoId,
                    'data_hora' => $data->format('Y-m-d').' '.$hora.':00',
                    'status' => $status,
                    'mensalista' => $ehMensalista,
                    'mensalista_id' => $mensalistaId,
                ]);
            }
        }

        // ─── Clientes para Repescagem (último agendamento > 30 dias atrás) ─────
        $clientesRepescagem = [
            ['nome' => 'Alex Borges',      'telefone' => '51988882001', 'dias' => 45],
            ['nome' => 'Bruno Cardoso',    'telefone' => '51988882002', 'dias' => 52],
            ['nome' => 'Cesar Magalhães',  'telefone' => '51988882003', 'dias' => 61],
            ['nome' => 'Daniel Vieira',    'telefone' => '51988882004', 'dias' => 38],
            ['nome' => 'Elvis Nascimento', 'telefone' => '51988882005', 'dias' => 75],
            ['nome' => 'Fábio Teixeira',   'telefone' => '51988882006', 'dias' => 33],
            ['nome' => 'Gustavo Meireles', 'telefone' => '51988882007', 'dias' => 90],
            ['nome' => 'Henrique Barros',  'telefone' => '51988882008', 'dias' => 42],
        ];

        foreach ($clientesRepescagem as $cr) {
            if (Agendamento::withoutGlobalScopes()->where('tenant_id', $tid)->where('cliente_telefone', $cr['telefone'])->exists()) {
                continue;
            }

            $profissional = $profissionais->random();
            $servicoId = $servicoIds[array_rand($servicoIds)];

            Agendamento::create([
                'tenant_id' => $tid,
                'cliente_nome' => $cr['nome'],
                'cliente_telefone' => $cr['telefone'],
                'profissional_id' => $profissional->id,
                'servico_id' => $servicoId,
                'data_hora' => now()->subDays($cr['dias'])->setTime(10, 0)->format('Y-m-d H:i:s'),
                'status' => 'concluido',
                'mensalista' => false,
            ]);

            if ($cr['dias'] > 50) {
                Agendamento::create([
                    'tenant_id' => $tid,
                    'cliente_nome' => $cr['nome'],
                    'cliente_telefone' => $cr['telefone'],
                    'profissional_id' => $profissional->id,
                    'servico_id' => $servicoId,
                    'data_hora' => now()->subDays($cr['dias'] + 30)->setTime(14, 0)->format('Y-m-d H:i:s'),
                    'status' => 'concluido',
                    'mensalista' => false,
                ]);
            }
        }

        // Agendamentos futuros (próximos 7 dias)
        for ($daysAhead = 1; $daysAhead <= 7; $daysAhead++) {
            $data = now()->addDays($daysAhead);
            if ($data->dayOfWeek === 0) {
                continue;
            }

            $qtdDia = rand(2, 4);
            shuffle($horarios);
            $horariosUsados = array_slice($horarios, 0, $qtdDia);

            foreach ($horariosUsados as $hora) {
                $profissional = $profissionais->random();
                $slotKey = $profissional->id.'-'.$data->format('Y-m-d').'-'.$hora;
                if (isset($usedSlots[$slotKey])) {
                    continue;
                }
                $usedSlots[$slotKey] = true;

                $av = $clientesAvulsos[array_rand($clientesAvulsos)];
                Agendamento::create([
                    'tenant_id' => $tid,
                    'cliente_nome' => $av['nome'],
                    'cliente_telefone' => $av['telefone'],
                    'profissional_id' => $profissional->id,
                    'servico_id' => $servicoIds[array_rand($servicoIds)],
                    'data_hora' => $data->format('Y-m-d').' '.$hora.':00',
                    'status' => rand(0, 1) ? 'pendente' : 'confirmado',
                    'mensalista' => false,
                ]);
            }
        }
    }
}
