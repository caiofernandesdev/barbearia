<x-filament-panels::page>

{{-- Filtros --}}
<x-filament::section :collapsible="false">
    <x-slot name="heading">Filtros</x-slot>
    {{ $this->filterForm }}
</x-filament::section>

{{-- Resumo Geral --}}
<x-filament::section>
    <x-slot name="heading">Resumo do Período</x-slot>
    {{ $this->resumoSchema }}
</x-filament::section>

{{-- Tabela nativa Filament: Detalhamento + Consolidação --}}
{{ $this->table }}

</x-filament-panels::page>
