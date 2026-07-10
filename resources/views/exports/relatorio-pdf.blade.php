<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #1f2937; background: #fff; }

        .header { background: #d97706; color: white; padding: 14px 20px; margin-bottom: 16px; }
        .header h1 { font-size: 16px; font-weight: bold; margin-bottom: 2px; }
        .header .sub { font-size: 10px; opacity: 0.9; }

        .kpi-row { display: flex; gap: 10px; margin-bottom: 16px; padding: 0 4px; }
        .kpi { flex: 1; background: #fffbeb; border: 1px solid #fcd34d; border-radius: 6px; padding: 10px 12px; text-align: center; }
        .kpi .val { font-size: 15px; font-weight: bold; color: #b45309; margin-bottom: 2px; }
        .kpi .lbl { font-size: 8px; color: #78716c; text-transform: uppercase; letter-spacing: 0.5px; }

        h2 { font-size: 10px; font-weight: bold; color: #374151; border-bottom: 2px solid #f59e0b;
             padding-bottom: 3px; margin: 14px 4px 6px; text-transform: uppercase; letter-spacing: 0.5px; }

        table { width: calc(100% - 8px); margin: 0 4px 14px; border-collapse: collapse; }
        thead tr { background: #f59e0b; }
        thead th { color: white; padding: 5px 7px; text-align: left; font-size: 8.5px; font-weight: bold; }
        thead th.r { text-align: right; }
        thead th.c { text-align: center; }
        tbody tr:nth-child(even) { background: #fefce8; }
        tbody td { padding: 4px 7px; font-size: 9px; border-bottom: 1px solid #f3f4f6; }
        tbody td.r { text-align: right; }
        tbody td.c { text-align: center; }
        tbody td.bold { font-weight: bold; }
        .green { color: #16a34a; font-weight: bold; }
        .amber { color: #b45309; font-weight: bold; }

        tfoot tr { background: #f3f4f6; }
        tfoot td { padding: 5px 7px; font-size: 9px; font-weight: bold; }

        .footer { text-align: right; font-size: 8px; color: #9ca3af; margin: 10px 4px 0; }

        .badge { display: inline-block; padding: 1px 6px; border-radius: 10px; font-size: 8px; }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-blue  { background: #dbeafe; color: #1e40af; }
        .badge-red   { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

<div class="header">
    <h1>{{ $config?->nome_barbearia ?? 'Barbearia' }} — Relatório de Desempenho</h1>
    <div class="sub">Período: {{ $inicio }} a {{ $fim }} &nbsp;|&nbsp; Gerado em {{ now()->format('d/m/Y H:i') }}</div>
</div>

{{-- KPIs --}}
<div class="kpi-row">
    <div class="kpi">
        <div class="val">{{ $totalAtendimentos }}</div>
        <div class="lbl">Atendimentos</div>
    </div>
    <div class="kpi">
        <div class="val">R$ {{ number_format($totalReceita, 2, ',', '.') }}</div>
        <div class="lbl">Receita Total</div>
    </div>
    <div class="kpi">
        <div class="val">R$ {{ number_format($totalAtendimentos > 0 ? $totalReceita / $totalAtendimentos : 0, 2, ',', '.') }}</div>
        <div class="lbl">Ticket Médio</div>
    </div>
    <div class="kpi">
        <div class="val">R$ {{ number_format($totalComissao, 2, ',', '.') }}</div>
        <div class="lbl">Comissões</div>
    </div>
</div>

{{-- Desempenho por Barbeiro --}}
<h2>Desempenho por Barbeiro</h2>
<table>
    <thead>
        <tr>
            <th>Barbeiro</th>
            <th class="c">Atendimentos</th>
            <th class="r">Receita</th>
            <th class="r">Ticket Médio</th>
            <th class="r">Comissão</th>
            <th class="c">Clientes</th>
        </tr>
    </thead>
    <tbody>
        @foreach($barbeiros as $b)
        <tr>
            <td class="bold">{{ $b['nome'] }}</td>
            <td class="c">{{ $b['total'] }}</td>
            <td class="r green">R$ {{ number_format($b['receita'], 2, ',', '.') }}</td>
            <td class="r">R$ {{ number_format($b['ticket'], 2, ',', '.') }}</td>
            <td class="r amber">R$ {{ number_format($b['comissao'], 2, ',', '.') }}</td>
            <td class="c">{{ $b['clientes'] }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td><strong>TOTAL</strong></td>
            <td class="c">{{ $totalAtendimentos }}</td>
            <td class="r green">R$ {{ number_format($totalReceita, 2, ',', '.') }}</td>
            <td class="r">R$ {{ number_format($totalAtendimentos > 0 ? $totalReceita / $totalAtendimentos : 0, 2, ',', '.') }}</td>
            <td class="r amber">R$ {{ number_format($totalComissao, 2, ',', '.') }}</td>
            <td></td>
        </tr>
    </tfoot>
</table>

{{-- Evolução Mensal --}}
<h2>Evolução Mensal (últimos 6 meses)</h2>
<table>
    <thead>
        <tr>
            <th>Mês</th>
            <th class="c">Atendimentos</th>
            <th class="r">Receita</th>
            <th class="r">Ticket Médio</th>
        </tr>
    </thead>
    <tbody>
        @foreach($evolucao as $e)
        <tr>
            <td class="bold">{{ $e['mes'] }}</td>
            <td class="c">{{ $e['total'] }}</td>
            <td class="r green">R$ {{ number_format($e['receita'], 2, ',', '.') }}</td>
            <td class="r">R$ {{ number_format($e['ticket'], 2, ',', '.') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

{{-- Lista de Agendamentos --}}
@if($agendamentos->count())
<h2>Agendamentos do Período ({{ $agendamentos->count() > 50 ? 'primeiros 50 de ' . $agendamentos->count() : $agendamentos->count() }} registros)</h2>
<table>
    <thead>
        <tr>
            <th>Data</th>
            <th>Hora</th>
            <th>Cliente</th>
            <th>Barbeiro</th>
            <th>Serviço</th>
            <th class="r">Valor</th>
            <th class="c">Status</th>
            @foreach($campos ?? [] as $campo)
            <th>{{ $campo->nome }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($agendamentos as $ag)
        <tr>
            <td>{{ $ag->data_hora->format('d/m/Y') }}</td>
            <td class="c">{{ $ag->data_hora->format('H:i') }}</td>
            <td class="bold">{{ $ag->cliente_nome }}</td>
            <td>{{ $ag->profissional?->nome ?? '-' }}</td>
            <td>{{ $ag->nomesServicos() ?: '-' }}</td>
            <td class="r green">R$ {{ number_format((float) ($ag->valor_total ?? $ag->servico?->preco ?? 0), 2, ',', '.') }}</td>
            <td class="c">
                @if($ag->status === 'concluido')
                    <span class="badge badge-green">Concluído</span>
                @elseif($ag->status === 'confirmado')
                    <span class="badge badge-blue">Confirmado</span>
                @else
                    <span class="badge badge-red">Cancelado</span>
                @endif
            </td>
            @foreach($campos ?? [] as $campo)
            <td>{{ $ag->dados_extras[$campo->slug] ?? '-' }}</td>
            @endforeach
        </tr>
        @endforeach
    </tbody>
</table>
@endif

<div class="footer">
    {{ $config?->nome_barbearia ?? 'Barbearia' }} &nbsp;·&nbsp; Relatório gerado em {{ now()->format('d/m/Y \à\s H:i') }}
</div>

</body>
</html>
