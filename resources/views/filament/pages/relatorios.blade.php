<x-filament-panels::page>

{{-- Filtros --}}
<x-filament::section :collapsible="false">
    <x-slot name="heading">Filtros</x-slot>
    {{ $this->filterForm }}
</x-filament::section>

{{-- KPI Stats --}}
{{ $this->statsSchema }}

{{-- Desempenho por barbeiro (InteractsWithTable nativo) --}}
{{ $this->table }}

{{-- Evolução Mensal — componente Livewire com tabela nativa Filament --}}
<livewire:admin.evolucao-mensal-table
    :wire:key="'evolucao-' . $this->filtroProfissional"
    :filtro-profissional="$this->filtroProfissional" />

{{-- Agendamentos do Período — componente Livewire com tabela nativa Filament --}}
<livewire:admin.agendamentos-relatorio-table
    :wire:key="'agendamentos-' . $this->dataInicio . '-' . $this->dataFim . '-' . $this->filtroProfissional"
    :data-inicio="$this->dataInicio"
    :data-fim="$this->dataFim"
    :filtro-profissional="$this->filtroProfissional" />

</x-filament-panels::page>
