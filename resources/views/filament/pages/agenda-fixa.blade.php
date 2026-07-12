<x-filament-panels::page>

    {{-- Estilos próprios (prefixo af-) — mobile-first, imunes ao CSS compilado --}}
    <style>
        .af-wrap { display: flex; flex-direction: column; gap: 16px; }
        .af-card {
            border: 1px solid rgba(120,120,120,.25);
            border-radius: 16px;
            padding: 16px;
            background: rgba(120,120,120,.04);
        }
        .af-card h3 { font-size: 15px; font-weight: 700; margin: 0 0 2px; }
        .af-card p.sub { font-size: 13px; opacity: .65; margin: 0 0 14px; }

        /* Campos: 1 coluna no mobile, 2 no tablet/desktop */
        .af-fields { display: grid; grid-template-columns: 1fr; gap: 14px; }
        @media (min-width: 640px) { .af-fields { grid-template-columns: 1fr 1fr; } }

        .af-field label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        .af-field select, .af-field input {
            width: 100%;
            padding: 11px 12px;
            border-radius: 10px;
            border: 1px solid rgba(120,120,120,.35);
            background: transparent;
            color: inherit;
            font-size: 16px; /* 16px evita zoom automático no iOS */
        }
        .af-field select:focus, .af-field input:focus {
            outline: 2px solid rgb(var(--primary-500, 245 158 11) / .5);
            outline-offset: 1px;
        }

        /* Ocorrências: cartão empilhado no mobile, linha no desktop */
        .af-oc {
            display: flex; flex-direction: column; gap: 10px;
            padding: 14px;
            border: 1px solid rgba(120,120,120,.25);
            border-radius: 14px;
            margin-bottom: 10px;
        }
        @media (min-width: 640px) { .af-oc { flex-direction: row; align-items: center; gap: 14px; } }

        .af-oc-head { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .af-badge {
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 999px;
            background: rgba(245,158,11,.15); color: #b45309; white-space: nowrap;
        }
        .af-oc-data { font-size: 15px; font-weight: 600; }
        .af-oc select {
            width: 100%;
            padding: 11px 12px; border-radius: 10px;
            border: 1px solid rgba(120,120,120,.35);
            background: transparent; color: inherit; font-size: 16px;
        }
        @media (min-width: 640px) { .af-oc select { max-width: 260px; margin-left: auto; } }

        .af-btn {
            width: 100%; margin-top: 6px;
            padding: 14px; border-radius: 12px; border: 0;
            background: rgb(245 158 11); color: #111827;
            font-size: 16px; font-weight: 700; cursor: pointer;
        }
        .af-btn:active { transform: scale(.99); }
        @media (min-width: 640px) { .af-btn { width: auto; padding: 12px 28px; } }
    </style>

    <div class="af-wrap">

        {{-- Passo 1: parâmetros --}}
        <div class="af-card">
            <h3>1 · Quem e quando</h3>
            <p class="sub">Escolha o cliente, o mês e o dia da semana em que ele costuma vir.</p>

            <div class="af-fields">
                <div class="af-field">
                    <label>Cliente</label>
                    <select wire:model.live="mensalistaId">
                        <option value="">Selecione…</option>
                        @foreach($this->mensalistas as $id => $nome)
                            <option value="{{ $id }}">{{ $nome }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="af-field">
                    <label>Profissional</label>
                    <select wire:model.live="profissionalId">
                        <option value="">Selecione…</option>
                        @foreach($this->profissionais as $id => $nome)
                            <option value="{{ $id }}">{{ $nome }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="af-field">
                    <label>Mês</label>
                    <select wire:model.live="mes">
                        @foreach($this->meses as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="af-field">
                    <label>Dia da semana</label>
                    <select wire:model.live="diaSemana">
                        <option value="">Selecione…</option>
                        @foreach($this->diasSemana as $num => $nome)
                            <option value="{{ $num }}">{{ $nome }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="af-field">
                    <label>Horário</label>
                    <input type="time" wire:model.live="hora">
                </div>
            </div>
        </div>

        {{-- Passo 2: serviço em cada data --}}
        @if(!empty($this->ocorrencias))
            <div class="af-card">
                <h3>2 · Serviço de cada dia</h3>
                <p class="sub">Defina o serviço em cada data. Deixe "não vem" nos dias em que o cliente não aparece.</p>

                @foreach($this->ocorrencias as $oc)
                    <div class="af-oc">
                        <div class="af-oc-head">
                            <span class="af-badge">{{ $oc['semana'] }}ª sem</span>
                            <span class="af-oc-data">{{ $oc['label'] }}</span>
                        </div>
                        <select wire:model="servicoPorData.{{ $oc['data'] }}">
                            <option value="">— não vem —</option>
                            @foreach($this->servicos as $id => $nome)
                                <option value="{{ $id }}">{{ $nome }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach

                <button type="button" class="af-btn" wire:click="gerar" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="gerar">✓ Gerar agendamentos</span>
                    <span wire:loading wire:target="gerar">Gerando…</span>
                </button>
            </div>
        @elseif($this->diaSemana !== null)
            <div class="af-card">
                <p class="sub" style="margin:0;">Nenhuma data encontrada para esse dia da semana neste mês.</p>
            </div>
        @endif

    </div>

</x-filament-panels::page>
