@extends('layouts.app')

@section('title', 'Agendamento Confirmado | ' . $nomeBarbearia)

@section('content')
<div class="min-h-screen bg-gray-900 flex items-center justify-center px-4">
    <div class="max-w-sm w-full">

        <div class="text-center mb-6">
            <div class="text-6xl mb-3">✅</div>
            <h1 class="text-white text-2xl font-bold">Agendado!</h1>
            <p class="text-gray-400 text-sm mt-1">Seu horário foi reservado com sucesso.</p>
        </div>

        <div class="bg-gray-800 rounded-2xl p-5 space-y-3 mb-6">
            <div class="flex items-center gap-3 text-sm">
                <span class="text-2xl">👤</span>
                <div>
                    <div class="text-gray-400 text-xs">Cliente</div>
                    <div class="text-white font-medium">{{ $agendamento->cliente_nome }}</div>
                </div>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <span class="text-2xl">✂️</span>
                <div>
                    <div class="text-gray-400 text-xs">Serviço</div>
                    <div class="text-white font-medium">{{ $agendamento->nomesServicos() }}</div>
                </div>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <span class="text-2xl">👨</span>
                <div>
                    <div class="text-gray-400 text-xs">Profissional</div>
                    <div class="text-white font-medium">{{ $agendamento->profissional->nome }}</div>
                </div>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <span class="text-2xl">📅</span>
                <div>
                    <div class="text-gray-400 text-xs">Data e hora</div>
                    <div class="text-white font-medium">{{ $agendamento->data_hora->format('d/m/Y') }} às {{ $agendamento->data_hora->format('H:i') }}</div>
                </div>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <span class="text-2xl">💰</span>
                <div>
                    <div class="text-gray-400 text-xs">Valor</div>
                    <div class="text-amber-400 font-semibold">R$ {{ number_format((float) ($agendamento->valor_total ?? $agendamento->servico?->preco ?? 0), 2, ',', '.') }}</div>
                </div>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <span class="text-2xl">⏱</span>
                <div>
                    <div class="text-gray-400 text-xs">Duração estimada</div>
                    <div class="text-white font-medium">{{ $agendamento->duracao_total_minutos ?? $agendamento->servico?->duracao_minutos }} minutos</div>
                </div>
            </div>
        </div>

        <div class="space-y-3">
            <form method="POST" action="{{ route('agendamento.meus-agendamentos', ['tenant' => $tenantSlug]) }}">
                @csrf
                <input type="hidden" name="telefone" value="{{ $agendamento->cliente_telefone }}">
                <button type="submit"
                    class="block w-full bg-amber-500 hover:bg-amber-600 text-white text-center font-semibold py-3 rounded-xl text-sm transition">
                    Ver meus agendamentos
                </button>
            </form>
            <a href="{{ route('agendamento.index', ['tenant' => $tenantSlug]) }}"
                class="block w-full bg-gray-700 hover:bg-gray-600 text-white text-center font-medium py-3 rounded-xl text-sm transition">
                Voltar ao início
            </a>
        </div>

    </div>
</div>
@endsection
