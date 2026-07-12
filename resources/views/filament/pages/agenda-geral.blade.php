<x-filament-panels::page>

    <style>
        .ag-field label { display:block; font-size:13px; font-weight:600; margin-bottom:6px; }
        .ag-field select {
            width:100%; max-width:360px; padding:11px 12px; border-radius:10px;
            border:1px solid rgba(0,0,0,.18); background:#fff; color:#111827;
            font-size:16px; color-scheme:light; box-sizing:border-box;
        }
        .dark .ag-field select { background:#26292f; color:#f3f4f6; border-color:rgba(255,255,255,.14); color-scheme:dark; }
    </style>

    {{-- Seletor de profissional --}}
    <x-filament::section>
        <x-slot name="heading">Ver agenda de</x-slot>
        <x-slot name="description">Escolha o profissional para ver a agenda dele, dia a dia.</x-slot>
        <div class="ag-field">
            <label>Profissional</label>
            <select wire:model.live="profissionalId">
                @foreach($this->profissionais as $id => $nome)
                    <option value="{{ $id }}">{{ $nome }}</option>
                @endforeach
            </select>
        </div>
    </x-filament::section>

    {{-- Agenda visual do profissional escolhido (mesmo componente do Meu Painel).
         O wire:key muda ao trocar o profissional, forçando o remount. --}}
    @if($this->profissionalId)
        <livewire:admin.agenda-dia-table
            :wire:key="'agenda-geral-' . $this->profissionalId"
            :profissional-id="$this->profissionalId"
            :heading="'Agenda — ' . $this->nomeProfissional" />
    @else
        <x-filament::section>
            <p class="text-sm text-gray-500">Nenhum profissional ativo cadastrado.</p>
        </x-filament::section>
    @endif

</x-filament-panels::page>
