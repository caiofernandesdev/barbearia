<x-filament-panels::page>

{{-- Agenda visual com dias + horários --}}
<livewire:admin.agenda-dia-table
    :wire:key="'agenda-barbeiro'"
    :profissional-id="auth()->user()->profissional_id" />

{{-- Tabela de próximos atendimentos com confirmação --}}
{{ $this->table }}

</x-filament-panels::page>
