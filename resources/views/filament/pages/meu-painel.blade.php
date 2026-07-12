<x-filament-panels::page>

{{-- Link de agendamento para o profissional compartilhar --}}
@php $tenant = app()->bound('current_tenant') ? app('current_tenant') : null; @endphp
@if($tenant)
    @php $linkAgenda = url('/'.$tenant->slug); @endphp
    <x-filament::section>
        <x-slot name="heading">Link de agendamento</x-slot>
        <x-slot name="description">Envie para seus clientes agendarem com você.</x-slot>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <code style="padding:9px 12px;border-radius:8px;background:rgba(120,120,120,.15);font-size:14px;user-select:all;flex:1;min-width:200px;">{{ $linkAgenda }}</code>
            <button type="button"
                style="padding:9px 16px;border-radius:8px;background:#f59e0b;color:#111827;font-weight:600;font-size:14px;border:0;cursor:pointer;"
                onclick="navigator.clipboard.writeText('{{ $linkAgenda }}').then(() => { this.textContent = '✓ Copiado!'; setTimeout(() => this.textContent = 'Copiar link', 2000); })">Copiar link</button>
            <a href="{{ $linkAgenda }}" target="_blank" style="font-size:13px;text-decoration:underline;opacity:.8;">abrir</a>
        </div>
    </x-filament::section>
@endif

{{-- Agenda visual com dias + horários --}}
<livewire:admin.agenda-dia-table
    :wire:key="'agenda-barbeiro'"
    :profissional-id="auth()->user()->profissional_id" />

{{-- Tabela de próximos atendimentos com confirmação --}}
{{ $this->table }}

</x-filament-panels::page>
