{{-- Estilos inline: imunes ao CSS compilado do Filament --}}
<div>
    @forelse($pagamentos as $p)
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;
                    padding:10px 12px; border-radius:10px; margin-bottom:8px;
                    background:rgba(120,120,120,.08); border:1px solid rgba(120,120,120,.15);">
            <div>
                <div style="font-weight:600; font-size:15px;">
                    R$ {{ number_format((float) $p->valor, 2, ',', '.') }}
                </div>
                <div style="font-size:12px; opacity:.7;">
                    Competência {{ \Carbon\Carbon::parse($p->competencia.'-01')->locale('pt_BR')->isoFormat('MMM/YYYY') }}
                    · {{ ucfirst($p->forma) }}
                    @if($p->observacao) · {{ $p->observacao }} @endif
                </div>
            </div>
            <div style="font-size:13px; opacity:.8; white-space:nowrap;">
                {{ $p->pago_em->format('d/m/Y') }}
            </div>
        </div>
    @empty
        <div style="text-align:center; padding:24px; opacity:.6;">
            Nenhum pagamento registrado ainda.
        </div>
    @endforelse
</div>
