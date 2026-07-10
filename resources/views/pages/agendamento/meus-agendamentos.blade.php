@extends('layouts.app')

@section('title', 'Meus Agendamentos | ' . $nomeBarbearia)

@section('content')
<div class="min-h-screen bg-gray-900 px-4 py-6 max-w-lg mx-auto">

    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('agendamento.index', ['tenant' => $tenantSlug]) }}" class="text-gray-400 hover:text-white transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <h1 class="text-white font-bold text-lg">Meus Agendamentos</h1>
    </div>

    @if(session('sucesso'))
    <div class="bg-green-500 bg-opacity-20 border border-green-500 text-green-400 rounded-xl px-4 py-3 text-sm mb-4">
        {{ session('sucesso') }}
    </div>
    @endif

    @forelse($agendamentos as $ag)
    <div class="bg-gray-800 rounded-2xl p-4 mb-3">
        <div class="flex items-start justify-between mb-3">
            <div>
                <div class="text-white font-semibold text-sm">{{ $ag->nomesServicos() }}</div>
                <div class="text-gray-400 text-xs mt-0.5">com {{ $ag->profissional->nome }}</div>
            </div>
            <span class="text-xs font-medium px-3 py-1 rounded-full
                @if($ag->status === 'confirmado') bg-green-500 bg-opacity-20 text-green-400
                @else bg-yellow-500 bg-opacity-20 text-yellow-400 @endif">
                {{ $ag->status === 'confirmado' ? 'Confirmado' : 'Aguardando' }}
            </span>
        </div>
        <div class="flex items-center justify-between text-xs text-gray-400">
            <div class="flex items-center gap-1">
                <span>📅</span>
                {{ $ag->data_hora->format('d/m/Y') }} às {{ $ag->data_hora->format('H:i') }}
            </div>
            <div class="text-amber-400 font-semibold">R$ {{ number_format((float) ($ag->valor_total ?? $ag->servico?->preco ?? 0), 2, ',', '.') }}</div>
        </div>

        @if(in_array($ag->status, ['pendente', 'confirmado']))
        <form method="POST" action="{{ route('agendamento.cancelar', ['tenant' => $tenantSlug, 'agendamentoId' => $ag->id]) }}" class="mt-3"
            onsubmit="return confirm('Cancelar este agendamento?')">
            @csrf
            <input type="hidden" name="telefone" value="{{ $telefone }}">
            <button type="submit"
                class="w-full border border-red-500 text-red-400 hover:bg-red-500 hover:text-white py-2 rounded-xl text-xs font-medium transition">
                Cancelar agendamento
            </button>
        </form>
        @endif
    </div>
    @empty
    <div class="text-center py-12">
        <div class="text-5xl mb-3">📭</div>
        <p class="text-gray-400 text-sm">Nenhum agendamento ativo no momento.</p>
        <a href="{{ route('agendamento.index', ['tenant' => $tenantSlug]) }}"
            class="inline-block mt-4 bg-amber-500 hover:bg-amber-600 text-white font-medium px-6 py-2 rounded-xl text-sm transition">
            Fazer agendamento
        </a>
    </div>
    @endforelse

</div>
@endsection
