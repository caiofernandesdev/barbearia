<x-filament-panels::page>

    <x-filament::section>
        <x-slot name="heading">Resumo</x-slot>
        {{ $this->resumoSchema }}
    </x-filament::section>

    {{ $this->table }}

</x-filament-panels::page>
