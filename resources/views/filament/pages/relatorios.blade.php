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

{{-- Evolução Mensal --}}
<livewire:admin.evolucao-mensal-table
    :wire:key="'evolucao-' . $this->filtroProfissional . '-' . ($this->filtroStatus ?? 'todos')"
    :filtro-profissional="$this->filtroProfissional"
    :filtro-status="$this->filtroStatus" />

{{-- Agendamentos do Período --}}
<livewire:admin.agendamentos-relatorio-table
    :wire:key="'agendamentos-' . $this->dataInicio . '-' . $this->dataFim . '-' . $this->filtroProfissional . '-' . ($this->filtroStatus ?? 'todos')"
    :data-inicio="$this->dataInicio"
    :data-fim="$this->dataFim"
    :filtro-profissional="$this->filtroProfissional"
    :filtro-status="$this->filtroStatus" />

</x-filament-panels::page>
