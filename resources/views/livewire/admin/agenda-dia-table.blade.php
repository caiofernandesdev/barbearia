<div>
    <x-filament::section>
        <x-slot name="heading">{{ $heading }}</x-slot>

        {{-- Seletor de dias --}}
        <div style="display:flex; gap:8px; overflow-x:auto; padding-bottom:12px; -webkit-overflow-scrolling:touch;">
            @foreach($dias as $dia)
                <button
                    wire:click="selecionarDia('{{ $dia['data'] }}')"
                    style="
                        min-width:70px; padding:10px 8px; border-radius:12px; text-align:center; cursor:pointer; flex-shrink:0; transition:all 0.15s;
                        border:2px solid {{ $dia['selecionado'] ? '#f59e0b' : 'rgba(255,255,255,0.1)' }};
                        background:{{ $dia['selecionado'] ? '#f59e0b' : 'rgba(255,255,255,0.05)' }};
                        color:{{ $dia['selecionado'] ? '#000' : '#fff' }};
                    "
                >
                    <div style="font-size:11px; text-transform:uppercase; opacity:0.7;">{{ $dia['diaSemana'] }}</div>
                    <div style="font-size:22px; font-weight:bold; line-height:1.2;">{{ $dia['diaNum'] }}</div>
                    <div style="font-size:11px; text-transform:uppercase; opacity:0.7;">{{ $dia['mes'] }}</div>
                    @if($dia['totalAgs'] > 0)
                        <div style="margin-top:4px; font-size:10px; border-radius:8px; padding:1px 6px;
                            background:{{ $dia['selecionado'] ? 'rgba(0,0,0,0.2)' : 'rgba(245,158,11,0.2)' }};
                            color:{{ $dia['selecionado'] ? '#000' : '#f59e0b' }};">{{ $dia['totalAgs'] }}</div>
                    @endif
                </button>
            @endforeach
        </div>

        <div style="font-size:13px; color:#9ca3af; text-align:center; margin:12px 0;">
            {{ \Carbon\Carbon::parse($dataSelecionada)->locale('pt_BR')->isoFormat('dddd, D [de] MMMM') }}
        </div>

        {{-- Grid de horários --}}
        <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:8px;">
            @forelse($slots as $slot)
                @if($slot['ocupado'])
                    <div style="padding:12px 8px; border-radius:12px; text-align:center; background:rgba(239,68,68,0.15); border:1px solid rgba(239,68,68,0.3);">
                        <div style="font-size:16px; font-weight:bold; color:#ef4444;">{{ $slot['hora'] }}</div>
                        <div style="font-size:11px; color:#fca5a5; margin-top:2px;">{{ $slot['cliente'] }}</div>
                        <div style="font-size:10px; color:#fca5a5; opacity:0.7;">{{ $slot['servico'] }}</div>
                        @if(!empty($slot['extras']))
                        <div style="font-size:9px; color:#fcd34d; opacity:0.9; margin-top:1px;">📝 {{ $slot['extras'] }}</div>
                        @endif
                    </div>
                @elseif($slot['passado'])
                    <div style="padding:12px 8px; border-radius:12px; text-align:center; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.05); opacity:0.3;">
                        <div style="font-size:16px; font-weight:bold; color:#6b7280;">{{ $slot['hora'] }}</div>
                    </div>
                @else
                    <button
                        wire:click="abrirAgendamento('{{ $slot['hora'] }}')"
                        style="padding:12px 8px; border-radius:12px; text-align:center; cursor:pointer; transition:all 0.15s;
                            background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.2);"
                        onmouseover="this.style.background='rgba(16,185,129,0.25)'"
                        onmouseout="this.style.background='rgba(16,185,129,0.1)'"
                    >
                        <div style="font-size:16px; font-weight:bold; color:#10b981;">{{ $slot['hora'] }}</div>
                        <div style="font-size:10px; color:#6ee7b7; margin-top:2px;">Disponível</div>
                    </button>
                @endif
            @empty
                <div style="grid-column:span 3; text-align:center; padding:24px; color:#9ca3af;">
                    Nenhum horário neste dia.
                </div>
            @endforelse
        </div>

        <div style="display:flex; gap:16px; justify-content:center; margin-top:16px; font-size:12px; color:#9ca3af;">
            <span><span style="color:#10b981;">●</span> Disponível</span>
            <span><span style="color:#ef4444;">●</span> Ocupado</span>
            <span><span style="color:#6b7280;">●</span> Passado</span>
        </div>
    </x-filament::section>

    {{-- Modal de agendamento rápido --}}
    @if($showModal)
    <div style="position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:50; display:flex; align-items:center; justify-content:center; padding:16px;"
         wire:click.self="fecharModal">
        <div style="background:#1f2937; border-radius:16px; padding:24px; max-width:400px; width:100%; border:1px solid rgba(255,255,255,0.1);">
            <h3 style="color:#fff; font-size:18px; font-weight:bold; margin-bottom:4px;">
                Agendar — {{ $horaSelecionada }}
            </h3>
            <p style="color:#9ca3af; font-size:13px; margin-bottom:20px;">
                {{ \Carbon\Carbon::parse($dataSelecionada)->locale('pt_BR')->isoFormat('dddd, D [de] MMMM') }}
            </p>

            <div style="display:flex; flex-direction:column; gap:12px;">
                <div>
                    <label style="color:#d1d5db; font-size:13px; display:block; margin-bottom:4px;">Nome do cliente *</label>
                    <input wire:model="clienteNome" type="text" placeholder="Nome completo"
                        style="width:100%; background:#374151; color:#fff; border:1px solid #4b5563; border-radius:10px; padding:10px 14px; font-size:14px; outline:none;">
                    @error('clienteNome') <span style="color:#ef4444; font-size:12px;">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label style="color:#d1d5db; font-size:13px; display:block; margin-bottom:4px;">Telefone *</label>
                    <input wire:model="clienteTelefone" type="tel" placeholder="(11) 99999-9999"
                        style="width:100%; background:#374151; color:#fff; border:1px solid #4b5563; border-radius:10px; padding:10px 14px; font-size:14px; outline:none;">
                    @error('clienteTelefone') <span style="color:#ef4444; font-size:12px;">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label style="color:#d1d5db; font-size:13px; display:block; margin-bottom:4px;">Serviço *</label>
                    <select wire:model="servicoId"
                        style="width:100%; background:#374151; color:#fff; border:1px solid #4b5563; border-radius:10px; padding:10px 14px; font-size:14px; outline:none;">
                        <option value="">Selecione...</option>
                        @foreach($servicos as $id => $nome)
                            <option value="{{ $id }}">{{ $nome }}</option>
                        @endforeach
                    </select>
                    @error('servicoId') <span style="color:#ef4444; font-size:12px;">{{ $message }}</span> @enderror
                </div>
            </div>

            <div style="display:flex; gap:8px; margin-top:20px;">
                <button wire:click="salvarAgendamento"
                    style="flex:1; background:#10b981; color:#fff; font-weight:600; padding:12px; border-radius:10px; border:none; cursor:pointer; font-size:14px;">
                    ✅ Confirmar
                </button>
                <button wire:click="fecharModal"
                    style="flex:1; background:#374151; color:#fff; padding:12px; border-radius:10px; border:1px solid #4b5563; cursor:pointer; font-size:14px;">
                    Cancelar
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
