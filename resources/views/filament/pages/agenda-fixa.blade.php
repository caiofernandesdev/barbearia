<x-filament-panels::page>

    <x-filament::section>
        <x-slot name="heading">Montar agenda do mês</x-slot>
        <x-slot name="description">
            Escolha o cliente, o mês e o dia da semana. Em cada data que aparecer, defina o serviço.
            Deixe em branco as datas em que o cliente não vem.
        </x-slot>

        {{-- Parâmetros --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Cliente</label>
                <select wire:model.live="mensalistaId"
                    class="fi-input block w-full rounded-lg border-gray-300 dark:border-white/10 dark:bg-white/5 text-sm">
                    <option value="">Selecione…</option>
                    @foreach($this->mensalistas as $id => $nome)
                        <option value="{{ $id }}">{{ $nome }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Mês</label>
                <select wire:model.live="mes"
                    class="fi-input block w-full rounded-lg border-gray-300 dark:border-white/10 dark:bg-white/5 text-sm">
                    @foreach($this->meses as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Dia da semana</label>
                <select wire:model.live="diaSemana"
                    class="fi-input block w-full rounded-lg border-gray-300 dark:border-white/10 dark:bg-white/5 text-sm">
                    <option value="">Selecione…</option>
                    @foreach($this->diasSemana as $num => $nome)
                        <option value="{{ $num }}">{{ $nome }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Profissional</label>
                <select wire:model.live="profissionalId"
                    class="fi-input block w-full rounded-lg border-gray-300 dark:border-white/10 dark:bg-white/5 text-sm">
                    <option value="">Selecione…</option>
                    @foreach($this->profissionais as $id => $nome)
                        <option value="{{ $id }}">{{ $nome }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Horário</label>
                <input type="time" wire:model.live="hora"
                    class="fi-input block w-full rounded-lg border-gray-300 dark:border-white/10 dark:bg-white/5 text-sm">
            </div>
        </div>
    </x-filament::section>

    {{-- Ocorrências do mês --}}
    @if(!empty($this->ocorrencias))
        <x-filament::section>
            <x-slot name="heading">
                {{ $this->diasSemana[$this->diaSemana] ?? '' }}s de {{ $this->meses[$this->mes] ?? '' }}
            </x-slot>
            <x-slot name="description">Defina o serviço de cada data (em branco = cliente não vem nesse dia).</x-slot>

            <div class="space-y-3">
                @foreach($this->ocorrencias as $oc)
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center justify-center rounded-lg bg-primary-500/10 text-primary-600 dark:text-primary-400 text-xs font-bold px-2.5 py-1 min-w-[3rem]">
                            {{ $oc['semana'] }}ª sem
                        </span>
                        <span class="font-medium text-sm min-w-[7rem]">{{ $oc['label'] }}</span>
                        <select wire:model="servicoPorData.{{ $oc['data'] }}"
                            class="fi-input block w-full max-w-xs rounded-lg border-gray-300 dark:border-white/10 dark:bg-white/5 text-sm">
                            <option value="">— não vem —</option>
                            @foreach($this->servicos as $id => $nome)
                                <option value="{{ $id }}">{{ $nome }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>

            <x-slot name="footerActions">
                <x-filament::button wire:click="gerar" icon="heroicon-o-check">
                    Gerar agendamentos
                </x-filament::button>
            </x-slot>
        </x-filament::section>
    @elseif($this->diaSemana !== null)
        <x-filament::section>
            <p class="text-sm text-gray-500">Nenhuma data encontrada para esse dia da semana neste mês.</p>
        </x-filament::section>
    @endif

</x-filament-panels::page>
