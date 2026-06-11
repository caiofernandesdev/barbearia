<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agendamento;
use App\Models\ConfiguracaoBarbearia;
use App\Models\Profissional;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RelatorioExportController extends Controller
{
    private function buscarAgendamentos(string $inicio, string $fim, ?int $pid)
    {
        return Agendamento::whereIn('status', ['confirmado', 'concluido'])
            ->whereBetween('data_hora', [$inicio . ' 00:00:00', $fim . ' 23:59:59'])
            ->when($pid, fn($q) => $q->where('profissional_id', $pid))
            ->with(['servico', 'profissional'])
            ->orderBy('data_hora', 'desc')
            ->get();
    }

    private function calcBarbeiros($ags, $profissionais): array
    {
        return $profissionais->map(function ($prof) use ($ags) {
            $profAgs = $ags->where('profissional_id', $prof->id);
            $receita = $profAgs->sum(fn($a) => $a->servico?->preco ?? 0);
            $total   = $profAgs->count();
            return [
                'nome'     => $prof->nome,
                'total'    => $total,
                'receita'  => $receita,
                'ticket'   => $total > 0 ? $receita / $total : 0,
                'comissao' => $receita * (($prof->comissao_percentual ?? 0) / 100),
                'clientes' => $profAgs->pluck('cliente_telefone')->unique()->count(),
            ];
        })->values()->all();
    }

    private function calcEvolucao(?int $pid): array
    {
        $nomes = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        $result = [];
        for ($i = 5; $i >= 0; $i--) {
            $d    = now()->subMonths($i);
            $ags  = Agendamento::whereIn('status', ['confirmado', 'concluido'])
                ->whereYear('data_hora', $d->year)
                ->whereMonth('data_hora', $d->month)
                ->when($pid, fn($q) => $q->where('profissional_id', $pid))
                ->with('servico')->get();
            $rec  = $ags->sum(fn($a) => $a->servico?->preco ?? 0);
            $result[] = [
                'mes'     => $nomes[$d->month - 1] . '/' . substr($d->year, 2),
                'total'   => $ags->count(),
                'receita' => $rec,
                'ticket'  => $ags->count() > 0 ? $rec / $ags->count() : 0,
            ];
        }
        return $result;
    }

    public function excel(Request $request)
    {
        $inicio = $request->get('inicio', now()->startOfMonth()->format('Y-m-d'));
        $fim    = $request->get('fim', now()->endOfMonth()->format('Y-m-d'));
        $pid    = $request->get('profissional') ? (int) $request->get('profissional') : null;

        $profissionais = Profissional::where('ativo', true)
            ->when($pid, fn($q) => $q->where('id', $pid))
            ->orderBy('nome')->get();

        $ags         = $this->buscarAgendamentos($inicio, $fim, $pid);
        $barbeiros   = $this->calcBarbeiros($ags, $profissionais);
        $totalRec    = $ags->sum(fn($a) => $a->servico?->preco ?? 0);
        $totalAg     = $ags->count();
        $evolucao    = $this->calcEvolucao($pid);

        $filename = 'relatorio-' . $inicio . '-a-' . $fim . '.csv';

        return response()->streamDownload(function () use ($barbeiros, $ags, $totalRec, $totalAg, $evolucao, $inicio, $fim) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8 para Excel

            fputcsv($out, ['RELATÓRIO DE DESEMPENHO'], ';');
            fputcsv($out, ['Período: ' . Carbon::parse($inicio)->format('d/m/Y') . ' a ' . Carbon::parse($fim)->format('d/m/Y')], ';');
            fputcsv($out, [''], ';');

            // Resumo geral
            fputcsv($out, ['RESUMO GERAL'], ';');
            fputcsv($out, ['Total de Atendimentos', $totalAg], ';');
            fputcsv($out, ['Receita Total', 'R$ ' . number_format($totalRec, 2, ',', '.')], ';');
            fputcsv($out, ['Ticket Médio', 'R$ ' . number_format($totalAg > 0 ? $totalRec / $totalAg : 0, 2, ',', '.')], ';');
            fputcsv($out, [''], ';');

            // Desempenho por barbeiro
            fputcsv($out, ['DESEMPENHO POR BARBEIRO'], ';');
            fputcsv($out, ['Barbeiro', 'Atendimentos', 'Receita (R$)', 'Ticket Médio (R$)', 'Comissão (R$)', 'Clientes Únicos'], ';');
            foreach ($barbeiros as $b) {
                fputcsv($out, [
                    $b['nome'],
                    $b['total'],
                    number_format($b['receita'], 2, ',', '.'),
                    number_format($b['ticket'], 2, ',', '.'),
                    number_format($b['comissao'], 2, ',', '.'),
                    $b['clientes'],
                ], ';');
            }
            fputcsv($out, [''], ';');

            // Evolução mensal
            fputcsv($out, ['EVOLUÇÃO MENSAL (últimos 6 meses)'], ';');
            fputcsv($out, ['Mês', 'Atendimentos', 'Receita (R$)', 'Ticket Médio (R$)'], ';');
            foreach ($evolucao as $e) {
                fputcsv($out, [
                    $e['mes'],
                    $e['total'],
                    number_format($e['receita'], 2, ',', '.'),
                    number_format($e['ticket'], 2, ',', '.'),
                ], ';');
            }
            fputcsv($out, [''], ';');

            // Lista completa de agendamentos
            fputcsv($out, ['LISTA DE AGENDAMENTOS DO PERÍODO'], ';');
            fputcsv($out, ['Data', 'Hora', 'Cliente', 'Telefone', 'Barbeiro', 'Serviço', 'Valor (R$)', 'Status'], ';');
            foreach ($ags as $ag) {
                fputcsv($out, [
                    $ag->data_hora->format('d/m/Y'),
                    $ag->data_hora->format('H:i'),
                    $ag->cliente_nome,
                    $ag->cliente_telefone,
                    $ag->profissional?->nome ?? '-',
                    $ag->servico?->nome ?? '-',
                    number_format($ag->servico?->preco ?? 0, 2, ',', '.'),
                    match ($ag->status) {
                        'concluido'  => 'Concluído',
                        'confirmado' => 'Confirmado',
                        'cancelado'  => 'Cancelado',
                        default      => $ag->status,
                    },
                ], ';');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function pdf(Request $request)
    {
        $inicio = $request->get('inicio', now()->startOfMonth()->format('Y-m-d'));
        $fim    = $request->get('fim', now()->endOfMonth()->format('Y-m-d'));
        $pid    = $request->get('profissional') ? (int) $request->get('profissional') : null;

        $config       = ConfiguracaoBarbearia::first();
        $profissionais = Profissional::where('ativo', true)
            ->when($pid, fn($q) => $q->where('id', $pid))
            ->orderBy('nome')->get();

        $ags         = $this->buscarAgendamentos($inicio, $fim, $pid);
        $barbeiros   = $this->calcBarbeiros($ags, $profissionais);
        $totalRec    = $ags->sum(fn($a) => $a->servico?->preco ?? 0);
        $totalAg     = $ags->count();
        $totalCom    = array_sum(array_column($barbeiros, 'comissao'));
        $evolucao    = $this->calcEvolucao($pid);

        $pdf = Pdf::loadView('exports.relatorio-pdf', [
            'config'            => $config,
            'inicio'            => Carbon::parse($inicio)->format('d/m/Y'),
            'fim'               => Carbon::parse($fim)->format('d/m/Y'),
            'barbeiros'         => $barbeiros,
            'totalAtendimentos' => $totalAg,
            'totalReceita'      => $totalRec,
            'totalComissao'     => $totalCom,
            'evolucao'          => $evolucao,
            'agendamentos'      => $ags->take(50),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('relatorio-' . $inicio . '-a-' . $fim . '.pdf');
    }
}
