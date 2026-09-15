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
        .agx-daychip--past { opacity:.5; }
        .agx-daychip--past:hover { opacity:1; }
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
        .agx-past .agx-sub { color:#94a3b8; font-size:10px; }
        .dark .agx-past { background:rgba(255,255,255,.04); border-color:rgba(255,255,255,.07); }
        .dark .agx-past .agx-hora { color:#64748b; }
        .agx-past--btn { cursor:pointer; border-style:dashed; }
        .agx-past--btn:hover { background:#eef2f7; border-color:#cbd5e1; }
        .dark .agx-past--btn:hover { background:rgba(255,255,255,.09); }

        /* ── botão inverter agendamentos ── */
        .agx-swapbtn {
            display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:600;
            padding:8px 14px; border-radius:10px; cursor:pointer;
            background:#f1f5f9; color:#334155; border:1px solid #e2e8f0; transition:all .15s;
        }
        .agx-swapbtn:hover { background:#e2e8f0; border-color:#cbd5e1; }
        .dark .agx-swapbtn { background:rgba(255,255,255,.06); color:#e2e8f0; border-color:rgba(255,255,255,.12); }
        .dark .agx-swapbtn:hover { background:rgba(255,255,255,.12); }

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

        /* ── toggle Slots ⇄ Agenda ── */
        .agx-modo { display:inline-flex; background:#f1f5f9; border-radius:10px; padding:3px; gap:2px; }
        .dark .agx-modo { background:rgba(255,255,255,.06); }
        .agx-modo-btn { font-size:13px; font-weight:600; padding:6px 12px; border-radius:8px; border:none; background:transparent; color:#64748b; cursor:pointer; transition:all .15s; }
        .agx-modo-btn--on { background:#fff; color:#111827; box-shadow:0 1px 2px rgba(0,0,0,.12); }
        .dark .agx-modo-btn { color:#94a3b8; }
        .dark .agx-modo-btn--on { background:#374151; color:#fff; }

        /* ── timeline (vista de agenda) ── */
        .agx-tl-body { position:relative; margin-left:56px; border-left:1px solid #e5e7eb; }
        .dark .agx-tl-body { border-color:rgba(255,255,255,.10); }
        .agx-tl-hour { position:absolute; left:0; right:0; border-top:1px solid #e5e7eb; pointer-events:none; }
        .dark .agx-tl-hour { border-color:rgba(255,255,255,.10); }
        .agx-tl-subhour { position:absolute; left:0; right:0; border-top:1px dashed #eceff4; pointer-events:none; }
        .dark .agx-tl-subhour { border-color:rgba(255,255,255,.07); }
        .agx-tl-hourlabel { position:absolute; left:-56px; width:48px; text-align:right; font-size:11px; color:#94a3b8; transform:translateY(-7px); pointer-events:none; }
        .agx-tl-now { position:absolute; left:0; right:0; border-top:2px solid #ef4444; z-index:6; pointer-events:none; }
        .agx-tl-nowlabel { position:absolute; left:-56px; background:#ef4444; color:#fff; font-size:10px; font-weight:700; padding:1px 5px; border-radius:6px; transform:translateY(-50%); z-index:6; pointer-events:none; }
        .agx-tl-block { position:absolute; left:5px; right:5px; border-radius:8px; padding:5px 8px; overflow:hidden; cursor:pointer; border-left:3px solid; z-index:3; }
        .agx-tl-block .t { font-size:11px; opacity:.9; }
        .agx-tl-block .c { font-weight:600; font-size:12px; line-height:1.3; margin-top:2px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
        .agx-b-confirmado { background:#dbeafe; border-color:#3b82f6; color:#1e3a8a; }
        .agx-b-pendente   { background:#fef3c7; border-color:#f59e0b; color:#78350f; }
        .agx-b-concluido  { background:#eef2f7; border-color:#94a3b8; color:#334155; }
        .dark .agx-b-confirmado { background:rgba(59,130,246,.22); color:#bfdbfe; }
        .dark .agx-b-pendente   { background:rgba(245,158,11,.20); color:#fde68a; }
        .dark .agx-b-concluido  { background:rgba(148,163,184,.18); color:#cbd5e1; }
    </style>

    <x-filament::section>
        <x-slot name="heading">{{ $heading }}</x-slot>

        {{-- Toggle Slots ⇄ Agenda + inverter --}}
        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; margin-bottom:12px; flex-wrap:wrap;">
            <div class="agx-modo">
                <button type="button" wire:click="$set('modoAgenda','slots')"
                    class="agx-modo-btn {{ $modoAgenda === 'slots' ? 'agx-modo-btn--on' : '' }}">▦ {{ __('painel.agenda.modo_slots') }}</button>
                <button type="button" wire:click="$set('modoAgenda','agenda')"
                    class="agx-modo-btn {{ $modoAgenda === 'agenda' ? 'agx-modo-btn--on' : '' }}">📅 {{ __('painel.agenda.modo_agenda') }}</button>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                @if($this->podeIndisponibilidade)
                    <button type="button" wire:click="mountAction('indisponibilidade')" class="agx-swapbtn">
                        🔒 {{ __('painel.agenda.btn_indisponibilidade') }}
                    </button>
                @endif
                @if($this->podeCancelar)
                    <button type="button" wire:click="mountAction('inverter')" class="agx-swapbtn">
                        🔄 {{ __('painel.agenda.btn_inverter') }}
                    </button>
                @endif
            </div>
        </div>

        {{-- Seletor de dias (carrossel: passado ↔ futuro; rola até o selecionado) --}}
        <div style="display:flex; gap:8px; overflow-x:auto; padding-bottom:12px; -webkit-overflow-scrolling:touch;"
            x-data x-init="$nextTick(() => $el.querySelector('.agx-daychip--sel')?.scrollIntoView({inline:'center', block:'nearest'}))">
            @foreach($dias as $dia)
                <button wire:click="selecionarDia('{{ $dia['data'] }}')"
                    class="agx-daychip {{ $dia['selecionado'] ? 'agx-daychip--sel' : '' }} {{ $dia['passado'] && ! $dia['selecionado'] ? 'agx-daychip--past' : '' }}">
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
            {{ \Carbon\Carbon::parse($dataSelecionada)->locale(app()->getLocale())->isoFormat('dddd, D MMMM') }}
        </div>

        {{-- Grid de horários (modo slots) --}}
        @if($modoAgenda === 'slots')
        <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:8px;">
            @forelse($slots as $slot)
                @if($slot['ocupado'])
                    @if($slot['cancelavel'])
                        <button wire:click="abrirCancelamento({{ $slot['agendamento_id'] }})"
                            title="{{ __('painel.agenda.cancelar_title', ['nome' => $slot['cliente']]) }}"
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
                        <div class="agx-sub">🔒 {{ __('painel.agenda.indisponivel') }}</div>
                        @if(!empty($slot['motivo']))
                            <div class="agx-sub2">{{ $slot['motivo'] }}</div>
                        @endif
                    </div>
                @elseif($slot['passado'])
                    <button wire:click="mountAction('agendar', { hora: '{{ $slot['hora'] }}', passado: true })"
                        title="{{ __('painel.agenda.passado_title') }}"
                        class="agx-slot agx-past agx-past--btn">
                        <div class="agx-hora">{{ $slot['hora'] }}</div>
                        <div class="agx-sub">{{ __('painel.agenda.passado_marcar') }}</div>
                    </button>
                @else
                    <button wire:click="mountAction('agendar', { hora: '{{ $slot['hora'] }}' })" class="agx-slot agx-avail">
                        <div class="agx-hora">{{ $slot['hora'] }}</div>
                        <div class="agx-sub">{{ __('painel.agenda.disponivel') }}</div>
                    </button>
                @endif
            @empty
                <div class="agx-muted" style="grid-column:span 3; text-align:center; padding:24px;">
                    {{ __('painel.agenda.nenhum_horario') }}
                </div>
            @endforelse
        </div>

        <div class="agx-legend agx-muted">
            <span><span style="color:#10b981;">●</span> {{ __('painel.agenda.leg_disponivel') }}</span>
            <span><span style="color:#ef4444;">●</span> {{ __('painel.agenda.leg_ocupado') }}</span>
            <span><span style="color:#8b5cf6;">●</span> {{ __('painel.agenda.leg_indisponivel') }}</span>
            <span><span style="color:#94a3b8;">●</span> {{ __('painel.agenda.leg_passado') }}</span>
        </div>

        {{-- Vista de agenda (timeline por horário) --}}
        @else
        <div style="position:relative; overflow-x:hidden;">
            <div class="agx-tl-body" style="height:{{ $timeline['alturaTotal'] }}px"
                data-inicio="{{ $timeline['inicioMin'] }}" data-px="{{ $timeline['pxPorMin'] }}"
                onclick="agxTimelineClick(event, this)">

                @foreach($timeline['subLinhas'] as $top)
                    <div class="agx-tl-subhour" style="top:{{ $top }}px"></div>
                @endforeach

                @foreach($timeline['horas'] as $h)
                    <div class="agx-tl-hour" style="top:{{ $h['top'] }}px"></div>
                    <div class="agx-tl-hourlabel" style="top:{{ $h['top'] }}px">{{ $h['label'] }}</div>
                @endforeach

                @if(!is_null($timeline['agoraTop']))
                    <div class="agx-tl-now" style="top:{{ $timeline['agoraTop'] }}px"></div>
                    <div class="agx-tl-nowlabel" style="top:{{ $timeline['agoraTop'] }}px">{{ $timeline['agoraLabel'] }}</div>
                @endif

                @foreach($timeline['blocos'] as $b)
                    <div class="agx-tl-block agx-b-{{ $b['status'] }}"
                        style="top:{{ $b['top'] }}px; height:{{ $b['height'] }}px"
                        @if($b['cancelavel'])
                            wire:click.stop="abrirCancelamento({{ $b['id'] }})"
                            title="Cancelar o agendamento de {{ $b['cliente'] }}"
                        @else
                            onclick="event.stopPropagation()"
                        @endif>
                        <div class="t">{{ $b['inicio'] }} - {{ $b['fim'] }}</div>
                        <div class="c">{{ $b['servico'] }} · {{ $b['cliente'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
        <p class="agx-muted" style="text-align:center; font-size:12px; margin-top:12px;">
            {{ __('painel.agenda.timeline_hint') }}
        </p>
        @endif
    </x-filament::section>

    {{-- Modal de cancelamento --}}
    @if($showCancelModal)
    <div class="agx-backdrop" wire:click.self="fecharCancelModal">
        <div class="agx-modal">
            <h3>{{ __('painel.agenda.cancel_titulo') }}</h3>
            <p class="agx-modal-text">{{ $cancelarResumo }}</p>
            <p class="agx-modal-hint">{{ __('painel.agenda.cancel_hint') }}</p>
            <div style="display:flex; gap:8px;">
                <button wire:click="confirmarCancelamento" class="agx-btn-primary" style="background:#ef4444;">{{ __('painel.agenda.cancel_sim') }}</button>
                <button wire:click="fecharCancelModal" class="agx-btn-secondary">{{ __('painel.agenda.cancel_voltar') }}</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Caixa de agendamento rápido (Filament Action: busca cliente, multi-serviço) --}}
    <x-filament-actions::modals />
</div>

<script>
    // Toque num espaço livre da timeline → abre a caixa de agendar naquele horário.
    // As marcas de hora têm pointer-events:none; os blocos usam stop/stopPropagation.
    window.agxTimelineClick = function (ev, el) {
        if (ev.target !== el) return;
        const inicioMin = parseInt(el.dataset.inicio, 10);
        const px = parseFloat(el.dataset.px);
        if (!px) return;
        let min = inicioMin + Math.round((ev.offsetY / px) / 5) * 5; // arredonda p/ 5 min
        if (min < 0) min = 0;
        const hh = String(Math.floor(min / 60)).padStart(2, '0');
        const mm = String(min % 60).padStart(2, '0');
        const root = el.closest('[wire\\:id]');
        if (root && window.Livewire) {
            window.Livewire.find(root.getAttribute('wire:id')).mountAction('agendar', { hora: hh + ':' + mm });
        }
    };
</script>
