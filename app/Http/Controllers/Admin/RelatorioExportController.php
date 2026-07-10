<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agendamento;
use App\Models\CampoPersonalizado;
use App\Models\ConfiguracaoBarbearia;
use App\Models\Profissional;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RelatorioExportController extends Controller
{
    public function __construct()
    {
        $user = auth('admin')->user();
        if ($user && $user->tenant_id) {
            $tenant = Tenant::find($user->tenant_id);
            if ($tenant) {
                app()->instance('current_tenant', $tenant);
            }
        }
    }

    private function resolveStatuses(?string $status): array
    {
        return match ($status) {
            'confirmado' => ['confirmado'],
            'concluido' => ['concluido'],
            'pendente' => ['pendente'],
            'cancelado' => ['cancelado'],
            default => ['confirmado', 'concluido'],
        };
    }

    private function buscarAgendamentos(string $inicio, string $fim, ?int $pid, array $statuses)
    {
        return Agendamento::whereIn('status', $statuses)
            ->whereBetween('data_hora', [$inicio.' 00:00:00', $fim.' 23:59:59'])
            ->when($pid, fn ($q) => $q->where('profissional_id', $pid))
            ->with(['servico', 'servicos', 'profissional'])
            ->orderBy('data_hora', 'desc')
            ->get();
    }

    /** Campos personalizados ativos do tenant — viram colunas extras nos exports. */
    private function camposExtras()
    {
        return CampoPersonalizado::where('ativo', true)->orderBy('ordem')->get();
    }

    private function calcBarbeiros($ags, $profissionais): array
    {
        return $profissionais->map(function ($prof) use ($ags) {
            $profAgs = $ags->where('profissional_id', $prof->id);
            $receita = $profAgs->sum(fn ($a) => $a->valor_total ?? $a->servico?->preco ?? 0);
            $total = $profAgs->count();
            $perc = (float) ($prof->comissao_percentual ?? 0);

            return [
                'nome' => $prof->nome,
                'total' => $total,
                'receita' => $receita,
                'ticket' => $total > 0 ? $receita / $total : 0,
                'comissao' => round($receita * ($perc / 100), 2),
                'perc' => $perc,
                'clientes' => $profAgs->pluck('cliente_telefone')->unique()->count(),
            ];
        })->values()->all();
    }

    private function calcEvolucao(?int $pid, array $statuses): array
    {
        $nomes = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        $result = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = now()->subMonths($i);
            $ags = Agendamento::whereIn('status', $statuses)
                ->whereYear('data_hora', $d->year)
                ->whereMonth('data_hora', $d->month)
                ->when($pid, fn ($q) => $q->where('profissional_id', $pid))
                ->with('servico')->get();
            $rec = $ags->sum(fn ($a) => $a->valor_total ?? $a->servico?->preco ?? 0);
            $result[] = [
                'mes' => $nomes[$d->month - 1].'/'.substr($d->year, 2),
                'total' => $ags->count(),
                'receita' => $rec,
                'ticket' => $ags->count() > 0 ? $rec / $ags->count() : 0,
            ];
        }

        return $result;
    }

    public function excel(Request $request)
    {
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;
        if (! $tenant?->hasFeature('relatorios')) {
            abort(403, 'Módulo de relatórios não disponível no seu plano.');
        }

        $inicio = $request->get('inicio', now()->startOfMonth()->format('Y-m-d'));
        $fim = $request->get('fim', now()->endOfMonth()->format('Y-m-d'));
        $pid = $request->get('profissional') ? (int) $request->get('profissional') : null;
        $statuses = $this->resolveStatuses($request->get('status'));

        $profissionais = Profissional::where('ativo', true)
            ->when($pid, fn ($q) => $q->where('id', $pid))
            ->orderBy('nome')->get();

        $ags = $this->buscarAgendamentos($inicio, $fim, $pid, $statuses);
        $barbeiros = $this->calcBarbeiros($ags, $profissionais);
        $totalRec = $ags->sum(fn ($a) => $a->valor_total ?? $a->servico?->preco ?? 0);
        $totalAg = $ags->count();
        $totalCom = array_sum(array_column($barbeiros, 'comissao'));
        $evolucao = $this->calcEvolucao($pid, $statuses);

        $campos = $this->camposExtras();
        $filename = 'relatorio-'.$inicio.'-a-'.$fim.'.csv';

        return response()->streamDownload(function () use ($barbeiros, $ags, $totalRec, $totalAg, $totalCom, $evolucao, $inicio, $fim, $campos) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($out, ['RELATÓRIO DE DESEMPENHO'], ';');
            fputcsv($out, ['Período: '.Carbon::parse($inicio)->format('d/m/Y').' a '.Carbon::parse($fim)->format('d/m/Y')], ';');
            fputcsv($out, [''], ';');

            fputcsv($out, ['RESUMO GERAL'], ';');
            fputcsv($out, ['Total de Atendimentos', $totalAg], ';');
            fputcsv($out, ['Receita Total', 'R$ '.number_format($totalRec, 2, ',', '.')], ';');
            fputcsv($out, ['Ticket Médio', 'R$ '.number_format($totalAg > 0 ? $totalRec / $totalAg : 0, 2, ',', '.')], ';');
            fputcsv($out, ['Total Comissões', 'R$ '.number_format($totalCom, 2, ',', '.')], ';');
            fputcsv($out, [''], ';');

            fputcsv($out, ['DESEMPENHO POR BARBEIRO'], ';');
            fputcsv($out, ['Barbeiro', 'Atendimentos', 'Receita (R$)', 'Ticket Médio (R$)', '% Comissão', 'Comissão (R$)', 'Clientes Únicos'], ';');
            foreach ($barbeiros as $b) {
                fputcsv($out, [
                    $b['nome'], $b['total'],
                    number_format($b['receita'], 2, ',', '.'),
                    number_format($b['ticket'], 2, ',', '.'),
                    $b['perc'].'%',
                    number_format($b['comissao'], 2, ',', '.'),
                    $b['clientes'],
                ], ';');
            }
            fputcsv($out, [''], ';');

            fputcsv($out, ['EVOLUÇÃO MENSAL (últimos 6 meses)'], ';');
            fputcsv($out, ['Mês', 'Atendimentos', 'Receita (R$)', 'Ticket Médio (R$)'], ';');
            foreach ($evolucao as $e) {
                fputcsv($out, [$e['mes'], $e['total'], number_format($e['receita'], 2, ',', '.'), number_format($e['ticket'], 2, ',', '.')], ';');
            }
            fputcsv($out, [''], ';');

            fputcsv($out, ['LISTA DE AGENDAMENTOS DO PERÍODO'], ';');
            fputcsv($out, array_merge(
                ['Data', 'Hora', 'Cliente', 'Telefone', 'Barbeiro', 'Serviço', 'Valor (R$)', 'Status'],
                $campos->pluck('nome')->all() // uma coluna por campo personalizado
            ), ';');
            foreach ($ags as $ag) {
                fputcsv($out, array_merge([
                    $ag->data_hora->format('d/m/Y'), $ag->data_hora->format('H:i'),
                    $ag->cliente_nome, $ag->cliente_telefone,
                    $ag->profissional?->nome ?? '-', $ag->nomesServicos() ?: '-',
                    number_format((float) ($ag->valor_total ?? $ag->servico?->preco ?? 0), 2, ',', '.'),
                    match ($ag->status) {
                        'concluido' => 'Concluído', 'confirmado' => 'Confirmado', 'cancelado' => 'Cancelado', 'pendente' => 'Pendente', default => $ag->status
                    },
                ], $campos->map(fn ($c) => $ag->dados_extras[$c->slug] ?? '-')->all()), ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function pdf(Request $request)
    {
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;
        if (! $tenant?->hasFeature('relatorios')) {
            abort(403, 'Módulo de relatórios não disponível no seu plano.');
        }

        $inicio = $request->get('inicio', now()->startOfMonth()->format('Y-m-d'));
        $fim = $request->get('fim', now()->endOfMonth()->format('Y-m-d'));
        $pid = $request->get('profissional') ? (int) $request->get('profissional') : null;
        $statuses = $this->resolveStatuses($request->get('status'));

        $config = ConfiguracaoBarbearia::first();
        $profissionais = Profissional::where('ativo', true)
            ->when($pid, fn ($q) => $q->where('id', $pid))
            ->orderBy('nome')->get();

        $ags = $this->buscarAgendamentos($inicio, $fim, $pid, $statuses);
        $barbeiros = $this->calcBarbeiros($ags, $profissionais);
        $totalRec = $ags->sum(fn ($a) => $a->valor_total ?? $a->servico?->preco ?? 0);
        $totalAg = $ags->count();
        $totalCom = array_sum(array_column($barbeiros, 'comissao'));
        $evolucao = $this->calcEvolucao($pid, $statuses);

        $pdf = Pdf::loadView('exports.relatorio-pdf', [
            'config' => $config,
            'inicio' => Carbon::parse($inicio)->format('d/m/Y'),
            'fim' => Carbon::parse($fim)->format('d/m/Y'),
            'barbeiros' => $barbeiros,
            'totalAtendimentos' => $totalAg,
            'totalReceita' => $totalRec,
            'totalComissao' => $totalCom,
            'evolucao' => $evolucao,
            'agendamentos' => $ags->take(50),
            'campos' => $this->camposExtras(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('relatorio-'.$inicio.'-a-'.$fim.'.pdf');
    }
}
