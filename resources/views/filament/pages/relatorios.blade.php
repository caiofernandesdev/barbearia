<x-filament-panels::page>

{{-- Filtros --}}
<x-filament::section :collapsible="false">
    <x-slot name="heading">Filtros</x-slot>
    {{ $this->filterForm }}
</x-filament::section>

{{-- KPI Stats (cards já filtrados pelo plano dentro do schema) --}}
{{ $this->statsSchema }}

{{-- Desempenho por barbeiro (InteractsWithTable nativo) --}}
@if($this->temRelatorio('desempenho_barbeiro'))
    {{ $this->table }}
@endif

{{-- Evolução Mensal --}}
@if($this->temRelatorio('evolucao_mensal'))
<livewire:admin.evolucao-mensal-table
    :wire:key="'evolucao-' . $this->filtroProfissional . '-' . ($this->filtroStatus ?? 'todos')"
    :filtro-profissional="$this->filtroProfissional"
    :filtro-status="$this->filtroStatus" />
@endif

{{-- Agendamentos do Período --}}
@if($this->temRelatorio('agendamentos_periodo'))
<livewire:admin.agendamentos-relatorio-table
    :wire:key="'agendamentos-' . $this->dataInicio . '-' . $this->dataFim . '-' . $this->filtroProfissional . '-' . ($this->filtroStatus ?? 'todos')"
    :data-inicio="$this->dataInicio"
    :data-fim="$this->dataFim"
    :filtro-profissional="$this->filtroProfissional"
    :filtro-status="$this->filtroStatus" />
@endif

</x-filament-panels::page>
