<div>
    {{-- Estilos próprios (não são utilitários Tailwind): adaptam-se ao tema
         claro e escuro do Filament, que alterna a classe .dark no <html>. --}}
    <style>
        .agx-muted { color:#64748b; }
        .dark .agx-muted { color:#94a3b8; }

        /* ── seletor de dias ── */
        .agx-daychip {
            min-width:70px; padding:10px 8px; border-radius:12px; text-align:center;
            cursor:pointer; flex-shrink:0; transition:all .15s;
            border:2px solid #e2e8f0; background:#f8fafc; color:#334155;
        }
        .dark .agx-daychip { border-color:rgba(255,255,255,.12); background:rgba(255,255,255,.05); color:#e2e8f0; }
        .agx-daychip:hover { border-color:#f59e0b; }
        .agx-daychip--sel, .agx-daychip--sel:hover { background:#f59e0b; border-color:#f59e0b; color:#111827; }
        .agx-dow, .agx-mon { font-size:11px; text-transform:uppercase; opacity:.7; }
        .agx-num { font-size:22px; font-weight:bold; line-height:1.2; }
        .agx-daybadge { margin-top:4px; font-size:10px; border-radius:8px; padding:1px 6px; background:rgba(245,158,11,.18); color:#b45309; }
        .dark .agx-daybadge { color:#fbbf24; }
        .agx-daychip--sel .agx-daybadge { background:rgba(0,0,0,.18); color:#111827; }

        /* ── slots ── */
        .agx-slot { width:100%; padding:12px 8px; border-radius:12px; text-align:center; border:1px solid transparent; transition:all .15s; }
        .agx-hora { font-size:16px; font-weight:bold; }
        .agx-sub  { font-size:11px; margin-top:2px; }
        .agx-sub2 { font-size:10px; opacity:.85; }
        .agx-extra{ font-size:9px; margin-top:1px; }

        .agx-avail { cursor:pointer; background:#ecfdf5; border-color:#a7f3d0; }
        .agx-avail:hover { background:#d1fae5; }
        .agx-avail .agx-hora { color:#059669; }
        .agx-avail .agx-sub  { color:#10b981; }
        .dark .agx-avail { background:rgba(16,185,129,.10); border-color:rgba(16,185,129,.25); }
        .dark .agx-avail:hover { background:rgba(16,185,129,.22); }
        .dark .agx-avail .agx-hora { color:#34d399; }
        .dark .agx-avail .agx-sub  { color:#6ee7b7; }

        .agx-busy { background:#fef2f2; border-color:#fecaca; }
        .agx-busy--btn { cursor:pointer; }
        .agx-busy--btn:hover { background:#fee2e2; }
        .agx-busy .agx-hora { color:#dc2626; }
        .agx-busy .agx-sub  { color:#ef4444; }
        .agx-busy .agx-sub2 { color:#dc2626; }
        .agx-busy .agx-extra{ color:#b45309; }
        .dark .agx-busy { background:rgba(239,68,68,.14); border-color:rgba(239,68,68,.30); }
        .dark .agx-busy--btn:hover { background:rgba(239,68,68,.24); }
        .dark .agx-busy .agx-hora { color:#f87171; }
        .dark .agx-busy .agx-sub, .dark .agx-busy .agx-sub2 { color:#fca5a5; }
        .dark .agx-busy .agx-extra{ color:#fcd34d; }

        .agx-indis { background:#f5f3ff; border-color:#ddd6fe; }
        .agx-indis .agx-hora { color:#7c3aed; }
        .agx-indis .agx-sub, .agx-indis .agx-sub2 { color:#8b5cf6; }
        .dark .agx-indis { background:rgba(139,92,246,.15); border-color:rgba(139,92,246,.35); }
        .dark .agx-indis .agx-hora { color:#a78bfa; }
        .dark .agx-indis .agx-sub, .dark .agx-indis .agx-sub2 { color:#c4b5fd; }

        .agx-past { background:#f8fafc; border-color:#e2e8f0; }
        .agx-past .agx-hora { color:#94a3b8; }
        .dark .agx-past { background:rgba(255,255,255,.04); border-color:rgba(255,255,255,.07); }
        .dark .agx-past .agx-hora { color:#64748b; }

        button.agx-slot { display:block; }

        /* ── legenda ── */
        .agx-legend { display:flex; gap:16px; justify-content:center; margin-top:16px; font-size:12px; flex-wrap:wrap; }

        /* ── modais (card claro/escuro sobre o backdrop) ── */
        .agx-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:50; display:flex; align-items:center; justify-content:center; padding:16px; }
        .agx-modal { background:#fff; border-radius:16px; padding:24px; max-width:400px; width:100%; border:1px solid #e5e7eb; box-shadow:0 20px 50px -12px rgba(0,0,0,.35); }
        .dark .agx-modal { background:#1f2937; border-color:rgba(255,255,255,.1); }
        .agx-modal h3 { color:#111827; font-size:18px; font-weight:bold; margin-bottom:8px; }
        .dark .agx-modal h3 { color:#fff; }
        .agx-modal-text { color:#374151; font-size:14px; margin-bottom:6px; }
        .dark .agx-modal-text { color:#d1d5db; }
        .agx-modal-hint { color:#6b7280; font-size:13px; margin-bottom:20px; }
        .dark .agx-modal-hint { color:#9ca3af; }
        .agx-label { color:#374151; font-size:13px; display:block; margin-bottom:4px; }
        .dark .agx-label { color:#d1d5db; }
        .agx-input { width:100%; background:#fff; color:#111827; border:1px solid #d1d5db; border-radius:10px; padding:10px 14px; font-size:14px; outline:none; }
        .dark .agx-input { background:#374151; color:#fff; border-color:#4b5563; }
        .agx-input:focus { border-color:#f59e0b; }
        .agx-btn-primary { flex:1; color:#fff; font-weight:600; padding:12px; border-radius:10px; border:none; cursor:pointer; font-size:14px; }
        .agx-btn-secondary { flex:1; padding:12px; border-radius:10px; cursor:pointer; font-size:14px; background:#f1f5f9; color:#334155; border:1px solid #e2e8f0; }
        .dark .agx-btn-secondary { background:#374151; color:#fff; border-color:#4b5563; }
        .agx-err { color:#ef4444; font-size:12px; }
    </style>

    <x-filament::section>
        <x-slot name="heading">{{ $heading }}</x-slot>

        {{-- Seletor de dias --}}
        <div style="display:flex; gap:8px; overflow-x:auto; padding-bottom:12px; -webkit-overflow-scrolling:touch;">
            @foreach($dias as $dia)
                <button wire:click="selecionarDia('{{ $dia['data'] }}')"
                    class="agx-daychip {{ $dia['selecionado'] ? 'agx-daychip--sel' : '' }}">
                    <div class="agx-dow">{{ $dia['diaSemana'] }}</div>
                    <div class="agx-num">{{ $dia['diaNum'] }}</div>
                    <div class="agx-mon">{{ $dia['mes'] }}</div>
                    @if($dia['totalAgs'] > 0)
                        <div class="agx-daybadge">{{ $dia['totalAgs'] }}</div>
                    @endif
                </button>
            @endforeach
        </div>

        <div class="agx-muted" style="font-size:13px; text-align:center; margin:12px 0;">
            {{ \Carbon\Carbon::parse($dataSelecionada)->locale('pt_BR')->isoFormat('dddd, D [de] MMMM') }}
        </div>

        {{-- Grid de horários --}}
        <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:8px;">
            @forelse($slots as $slot)
                @if($slot['ocupado'])
                    @if($slot['cancelavel'])
                        <button wire:click="abrirCancelamento({{ $slot['agendamento_id'] }})"
                            title="Cancelar o agendamento de {{ $slot['cliente'] }}"
                            class="agx-slot agx-busy agx-busy--btn">
                            <div class="agx-hora">{{ $slot['hora'] }}</div>
                            <div class="agx-sub">{{ $slot['cliente'] }}</div>
                            <div class="agx-sub2">{{ $slot['servico'] }}</div>
                            @if(!empty($slot['extras']))
                                <div class="agx-extra">📝 {{ $slot['extras'] }}</div>
                            @endif
                        </button>
                    @else
                        <div class="agx-slot agx-busy">
                            <div class="agx-hora">{{ $slot['hora'] }}</div>
                            <div class="agx-sub">{{ $slot['cliente'] }}</div>
                            <div class="agx-sub2">{{ $slot['servico'] }}</div>
                            @if(!empty($slot['extras']))
                                <div class="agx-extra">📝 {{ $slot['extras'] }}</div>
                            @endif
                        </div>
                    @endif
                @elseif($slot['indisponivel'])
                    <div class="agx-slot agx-indis">
                        <div class="agx-hora">{{ $slot['hora'] }}</div>
                        <div class="agx-sub">🔒 Indisponível</div>
                        @if(!empty($slot['motivo']))
                            <div class="agx-sub2">{{ $slot['motivo'] }}</div>
                        @endif
                    </div>
                @elseif($slot['passado'])
                    <div class="agx-slot agx-past">
                        <div class="agx-hora">{{ $slot['hora'] }}</div>
                    </div>
                @else
                    <button wire:click="abrirAgendamento('{{ $slot['hora'] }}')" class="agx-slot agx-avail">
                        <div class="agx-hora">{{ $slot['hora'] }}</div>
                        <div class="agx-sub">Disponível</div>
                    </button>
                @endif
            @empty
                <div class="agx-muted" style="grid-column:span 3; text-align:center; padding:24px;">
                    Nenhum horário neste dia.
                </div>
            @endforelse
        </div>

        <div class="agx-legend agx-muted">
            <span><span style="color:#10b981;">●</span> Disponível</span>
            <span><span style="color:#ef4444;">●</span> Ocupado</span>
            <span><span style="color:#8b5cf6;">●</span> Indisponível</span>
            <span><span style="color:#94a3b8;">●</span> Passado</span>
        </div>
    </x-filament::section>

    {{-- Modal de cancelamento --}}
    @if($showCancelModal)
    <div class="agx-backdrop" wire:click.self="fecharCancelModal">
        <div class="agx-modal">
            <h3>Cancelar agendamento?</h3>
            <p class="agx-modal-text">{{ $cancelarResumo }}</p>
            <p class="agx-modal-hint">O horário será liberado e o cliente avisado por WhatsApp.</p>
            <div style="display:flex; gap:8px;">
                <button wire:click="confirmarCancelamento" class="agx-btn-primary" style="background:#ef4444;">Sim, cancelar</button>
                <button wire:click="fecharCancelModal" class="agx-btn-secondary">Voltar</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal de agendamento rápido --}}
    @if($showModal)
    <div class="agx-backdrop" wire:click.self="fecharModal">
        <div class="agx-modal">
            <h3 style="margin-bottom:4px;">Agendar — {{ $horaSelecionada }}</h3>
            <p class="agx-modal-hint">
                {{ \Carbon\Carbon::parse($dataSelecionada)->locale('pt_BR')->isoFormat('dddd, D [de] MMMM') }}
            </p>

            <div style="display:flex; flex-direction:column; gap:12px;">
                <div>
                    <label class="agx-label">Nome do cliente *</label>
                    <input wire:model="clienteNome" type="text" placeholder="Nome completo" class="agx-input">
                    @error('clienteNome') <span class="agx-err">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="agx-label">Telefone *</label>
                    <input wire:model="clienteTelefone" type="tel" placeholder="(11) 99999-9999" class="agx-input">
                    @error('clienteTelefone') <span class="agx-err">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="agx-label">Serviço *</label>
                    <select wire:model="servicoId" class="agx-input">
                        <option value="">Selecione...</option>
                        @foreach($servicos as $id => $nome)
                            <option value="{{ $id }}">{{ $nome }}</option>
                        @endforeach
                    </select>
                    @error('servicoId') <span class="agx-err">{{ $message }}</span> @enderror
                </div>
            </div>

            <div style="display:flex; gap:8px; margin-top:20px;">
                <button wire:click="salvarAgendamento" class="agx-btn-primary" style="background:#10b981;">✅ Confirmar</button>
                <button wire:click="fecharModal" class="agx-btn-secondary">Cancelar</button>
            </div>
        </div>
    </div>
    @endif
</div>
