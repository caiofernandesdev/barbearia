<x-filament-panels::page>

    @php
        $t = $this->tenant;
        $meses = $this->meses;
        $cores = [
            'pago'      => ['#22c55e', 'rgba(34,197,94,.12)', 'Pago'],
            'atrasado'  => ['#ef4444', 'rgba(239,68,68,.12)', 'Atrasado'],
            'pendente'  => ['#f59e0b', 'rgba(245,158,11,.12)', 'A pagar'],
            'em_aberto' => ['#9ca3af', 'rgba(156,163,175,.10)', 'Em aberto'],
        ];
    @endphp

    <x-filament::section>
        <div style="display:flex; flex-wrap:wrap; gap:20px 40px; align-items:center;">
            <div>
                <div style="font-size:12px; opacity:.6; text-transform:uppercase; letter-spacing:.05em;">Plano</div>
                <div style="font-size:16px; font-weight:600;">{{ $t->plano?->nome ?? 'sem plano' }}</div>
            </div>
            <div>
                <div style="font-size:12px; opacity:.6; text-transform:uppercase; letter-spacing:.05em;">Mensalidade</div>
                <div style="font-size:16px; font-weight:600;">
                    R$ {{ number_format($t->valorMensal(), 2, ',', '.') }}
                    @if($t->temMensalidadeCustom())
                        <span style="font-size:11px; color:#818cf8;">★ personalizada</span>
                    @endif
                </div>
            </div>
            <div>
                <div style="font-size:12px; opacity:.6; text-transform:uppercase; letter-spacing:.05em;">Próximo vencimento</div>
                <div style="font-size:16px; font-weight:600;">
                    {{ $t->proximo_vencimento?->format('d/m/Y') ?? '—' }}
                </div>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Mensalidades mês a mês</x-slot>

        @if(empty($meses))
            <div style="text-align:center; padding:28px; opacity:.6;">
                Sem mensalidades a exibir. Defina o início da assinatura e o valor no cadastro do estabelecimento.
            </div>
        @else
            <div style="display:flex; flex-direction:column; gap:8px;">
                @foreach($meses as $mes)
                    @php [$cor, $bg, $rotuloEstado] = $cores[$mes['estado']]; @endphp
                    <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;
                                padding:14px 16px; border-radius:12px;
                                background:{{ $bg }}; border:1px solid {{ $cor }}33;">

                        {{-- Mês + vencimento --}}
                        <div style="flex:1; min-width:170px;">
                            <div style="font-size:16px; font-weight:600;">
                                {{ $mes['rotulo'] }}
                            </div>
                            <div style="font-size:12px; opacity:.65;">
                                Vence {{ $mes['vencimento']->format('d/m/Y') }}
                            </div>
                        </div>

                        {{-- Valor --}}
                        <div style="font-size:15px; font-weight:600; min-width:90px; text-align:right;">
                            R$ {{ number_format($mes['valor'], 2, ',', '.') }}
                        </div>

                        {{-- Situação --}}
                        <div style="min-width:150px; text-align:right;">
                            <span style="display:inline-block; padding:3px 10px; border-radius:999px; font-size:12px;
                                         font-weight:600; color:{{ $cor }}; background:{{ $cor }}22;">
                                {{ $rotuloEstado }}
                            </span>
                            @if($mes['pago'] && $mes['pagamento'])
                                <div style="font-size:11px; opacity:.7; margin-top:3px;">
                                    {{ $mes['pagamento']->pago_em->format('d/m/Y') }} · {{ ucfirst($mes['pagamento']->forma) }}
                                </div>
                            @endif
                        </div>

                        {{-- Ação --}}
                        <div style="min-width:150px; text-align:right;">
                            @if($mes['pagavel'])
                                <button type="button"
                                    wire:click="mountAction('registrarPagamento', { competencia: '{{ $mes['competencia'] }}' })"
                                    style="padding:8px 14px; border-radius:9px; border:0; cursor:pointer;
                                           background:#4f46e5; color:#fff; font-weight:600; font-size:13px;">
                                    Realizar pagamento
                                </button>
                            @elseif($mes['pago'] && $mes['pagamento']?->comprovante)
                                <a href="{{ route('superadmin.comprovante', $mes['pagamento']) }}" target="_blank"
                                   style="font-size:13px; text-decoration:underline; color:#818cf8;">📎 comprovante</a>
                            @elseif($mes['pago'])
                                <span style="font-size:12px; opacity:.5;">sem comprovante</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    <x-filament-actions::modals />

</x-filament-panels::page>
