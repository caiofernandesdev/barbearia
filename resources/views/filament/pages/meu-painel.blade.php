<x-filament-panels::page>

<x-filament::section :collapsible="false">
    <x-slot name="heading">Período</x-slot>
    {{ $this->filterForm }}
</x-filament::section>

{{ $this->statsSchema }}

<livewire:admin.agendamentos-relatorio-table
    :wire:key="'meup-' . $this->dataInicio . '-' . $this->dataFim"
    :data-inicio="$this->dataInicio"
    :data-fim="$this->dataFim"
    :filtro-profissional="auth()->user()->profissional_id" />

</x-filament-panels::page>
