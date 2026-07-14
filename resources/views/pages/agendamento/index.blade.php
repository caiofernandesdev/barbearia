@extends('layouts.app')

@section('title', 'Agendar Horário | ' . $nomeBarbearia)

@push('styles')
<style>
.carousel-track { -ms-overflow-style: none; scrollbar-width: none; touch-action: pan-x; }
.carousel-track::-webkit-scrollbar { display: none; }
</style>
@endpush

@section('content')
<div class="min-h-screen bg-gray-900 flex flex-col">

    {{-- Header --}}
    <div class="bg-gray-800 border-b border-gray-700 px-4 py-3 flex items-center gap-3">
        @if($logoUrl)
            <img src="{{ $logoUrl }}" alt="{{ $nomeBarbearia }}" class="w-10 h-10 rounded-full object-cover flex-shrink-0">
        @else
            <div class="w-10 h-10 rounded-full bg-amber-500 flex items-center justify-center text-white font-bold text-lg flex-shrink-0">{{ mb_strtoupper(mb_substr($nomeBarbearia, 0, 1)) }}</div>
        @endif
        <div>
            <div class="text-white font-semibold text-sm">{{ $nomeBarbearia }}</div>
            <div class="text-green-400 text-xs">● Online</div>
        </div>
    </div>

    {{-- Chat — todos os cards aparecem aqui, inline --}}
    <div class="flex-1 overflow-y-auto px-4 py-4 space-y-3 max-w-lg mx-auto w-full pb-8" id="chat-area">
        <div class="chat-bubble-in flex gap-2">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $nomeBarbearia }}" class="w-8 h-8 rounded-full object-cover flex-shrink-0">
            @else
                <div class="w-8 h-8 rounded-full bg-amber-500 flex-shrink-0 flex items-center justify-center text-white text-xs font-bold">{{ mb_strtoupper(mb_substr($nomeBarbearia, 0, 1)) }}</div>
            @endif
            <div class="bg-gray-700 text-white rounded-2xl rounded-tl-none px-4 py-3 max-w-xs text-sm leading-relaxed">
                Olá! 👋 Seja bem-vindo à <strong>{{ $nomeBarbearia }}</strong>!<br>
                Vamos agendar seu horário. Qual é o seu <strong>nome</strong>?
            </div>
        </div>
    </div>

</div>

{{-- Modal: agendamento ativo (avulso / mensalista) --}}
<div id="modal-agendamento-ativo" class="hidden fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 px-4">
    <div class="bg-gray-800 rounded-2xl p-6 max-w-sm w-full text-white">
        <div class="text-amber-400 text-4xl text-center mb-3">⚠️</div>
        <h2 class="text-center font-semibold text-lg mb-2">Agendamento Ativo</h2>
        <p class="text-gray-300 text-sm text-center mb-1">Você já tem um agendamento marcado:</p>
        <div id="info-agendamento-ativo" class="bg-gray-700 rounded-xl p-3 my-3 text-sm text-center"></div>
        <p class="text-gray-400 text-xs text-center mb-4">Cancele-o antes de fazer um novo agendamento.</p>
        <div class="flex gap-2">
            {{-- POST evita expor o telefone na URL --}}
            <form id="form-ver-agendamentos" method="POST" action="{{ route('agendamento.meus-agendamentos', ['tenant' => $tenantSlug]) }}" class="flex-1">
                @csrf
                <input type="hidden" id="input-telefone-modal" name="telefone" value="">
                <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-white text-center py-2 rounded-xl text-sm font-medium transition">Ver meus agendamentos</button>
            </form>
            <button onclick="fecharModalAtivo()" class="flex-1 bg-gray-600 hover:bg-gray-500 text-white py-2 rounded-xl text-sm font-medium transition">Fechar</button>
        </div>
    </div>
</div>

{{-- Modal: mensalista fixo — exibe próximos horários fixos --}}
<div id="modal-mensalista-fixo" class="hidden fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 px-4">
    <div class="bg-gray-800 rounded-2xl p-6 max-w-sm w-full text-white">
        <div class="text-amber-400 text-3xl text-center mb-2">📅</div>
        <h2 class="text-center font-semibold text-lg mb-1">Seus horários fixos</h2>
        <p class="text-gray-400 text-xs text-center mb-3">Próximas sessões já agendadas para você:</p>
        <div id="lista-horarios-fixos" class="space-y-2 max-h-52 overflow-y-auto mb-4"></div>
        <div class="flex gap-2">
            <button onclick="agendarAvulsoMensalistaFixo()"
                class="flex-1 bg-gray-600 hover:bg-gray-500 text-white py-2 rounded-xl text-sm transition">
                Agendar outro horário
            </button>
            <button onclick="fecharModalFixo()"
                class="flex-1 bg-amber-500 hover:bg-amber-600 text-white py-2 rounded-xl text-sm font-medium transition">
                Fechar
            </button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const tenantSlug = @json($tenantSlug);
const nomeBarbeariaInicial = "{{ mb_strtoupper(mb_substr($nomeBarbearia, 0, 1)) }}";
const logoUrl = @json($logoUrl);
const temListaEspera = @json($temListaEspera ?? false);

const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

let estado = 'nome';
let dadosCliente = {};
let profissionais = [];
let servicos = [];

let cardAtivo = null; // referência ao card interativo atual

// ─── Scroll ───────────────────────────────────────────────────────────────────

function scrollToBottom() {
    requestAnimationFrame(() => {
        const chatArea = document.getElementById('chat-area');
        chatArea.scrollTop = chatArea.scrollHeight;
    });
}

// ─── Mensagens ────────────────────────────────────────────────────────────────

// Adição síncrona — bot message sempre aparece ANTES do card
function addMensagemBot(texto) {
    const chatArea = document.getElementById('chat-area');
    const div = document.createElement('div');
    div.className = 'chat-bubble-in flex gap-2';
    const avatarBot = logoUrl
        ? `<img src="${logoUrl}" alt="" class="w-8 h-8 rounded-full object-cover flex-shrink-0">`
        : `<div class="w-8 h-8 rounded-full bg-amber-500 flex-shrink-0 flex items-center justify-center text-white text-xs font-bold">${nomeBarbeariaInicial}</div>`;
    div.innerHTML = `
        ${avatarBot}
        <div class="bg-gray-700 text-white rounded-2xl rounded-tl-none px-4 py-3 max-w-xs text-sm leading-relaxed">${texto}</div>
    `;
    chatArea.appendChild(div);
    scrollToBottom();
}

function addMensagemCliente(texto) {
    const chatArea = document.getElementById('chat-area');
    const div = document.createElement('div');
    div.className = 'chat-bubble-in flex justify-end';
    const bubble = document.createElement('div');
    bubble.className = 'bg-amber-500 text-white rounded-2xl rounded-tr-none px-4 py-3 max-w-xs text-sm';
    bubble.textContent = texto; // textContent — sem risco de injeção HTML
    div.appendChild(bubble);
    chatArea.appendChild(div);
    scrollToBottom();
}

// ─── Cards inline ─────────────────────────────────────────────────────────────

function criarCard(html) {
    if (cardAtivo) { cardAtivo.remove(); cardAtivo = null; }
    const chatArea = document.getElementById('chat-area');
    const wrapper = document.createElement('div');
    wrapper.className = 'w-full chat-bubble-in';
    wrapper.innerHTML = html;
    cardAtivo = wrapper;
    chatArea.appendChild(cardAtivo);
    scrollToBottom();
}

function formatarDataHora(str) {
    const d = new Date(str.replace(' ', 'T'));
    return d.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
}

function formatarDataSelecionada() {
    if (!dadosCliente.data) return 'Selecione um dia e horário';
    const d = new Date(dadosCliente.data + 'T12:00:00');
    const nomeDias  = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
    const nomeMeses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
                       'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    const base = `${nomeDias[d.getDay()]}, ${d.getDate()} de ${nomeMeses[d.getMonth()]} de ${d.getFullYear()}`;
    return dadosCliente.hora ? `${base} ${dadosCliente.hora}` : `${base} --`;
}

// Botão de voltar padronizado
function btnVoltar(estadoAnterior) {
    return `<button onclick="voltarPara('${estadoAnterior}')"
        class="w-full text-gray-500 hover:text-amber-400 text-xs py-2 transition text-center">← Voltar</button>`;
}

function voltarPara(novoEstado) {
    estado = novoEstado;
    renderInput();
}

function scrollCarrossel(trackId, direcao) {
    const track = document.getElementById(trackId);
    if (!track) return;
    track.scrollBy({ left: direcao * track.clientWidth * 0.75, behavior: 'smooth' });
}

// Escapa input do usuário antes de inserir em contexto HTML (previne XSS)
function sanitizeText(str) {
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
}

function mascaraTel(value) {
    const d = value.replace(/\D/g, '').slice(0, 11);
    if (d.length === 0) return '';
    if (d.length <= 2)  return `(${d}`;
    if (d.length <= 6)  return `(${d.slice(0,2)}) ${d.slice(2)}`;
    if (d.length <= 10) return `(${d.slice(0,2)}) ${d.slice(2,6)}-${d.slice(6)}`;
    return `(${d.slice(0,2)}) ${d.slice(2,7)}-${d.slice(7)}`;
}

// ─── Render dos cards ─────────────────────────────────────────────────────────

function renderInput() {

    // ── Nome ─────────────────────────────────────────────────────────────────
    if (estado === 'nome') {
        criarCard(`
            <div class="space-y-2">
                <input id="inp-nome" type="text" placeholder="Seu nome e sobrenome" maxlength="100"
                    class="w-full bg-gray-700 text-white rounded-2xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-amber-500 placeholder-gray-500">
                <button onclick="enviarNome()"
                    class="w-full bg-gray-600 hover:bg-amber-500 text-white rounded-2xl py-3 text-sm font-semibold transition">
                    Enviar
                </button>
            </div>
        `);
        setTimeout(() => {
            const inp = document.getElementById('inp-nome');
            inp?.focus();
            inp?.addEventListener('keypress', e => { if (e.key === 'Enter') enviarNome(); });
        }, 100);

    // ── Telefone ──────────────────────────────────────────────────────────────
    } else if (estado === 'telefone') {
        criarCard(`
            <div class="space-y-2">
                <input id="inp-tel" type="tel" placeholder="(00) 00000-0000" maxlength="20"
                    class="w-full bg-gray-700 text-white rounded-2xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-amber-500 placeholder-gray-500">
                <button onclick="enviarTelefone()"
                    class="w-full bg-gray-600 hover:bg-amber-500 text-white rounded-2xl py-3 text-sm font-semibold transition">
                    Enviar
                </button>
                ${btnVoltar('nome')}
            </div>
        `);
        setTimeout(() => {
            const inp = document.getElementById('inp-tel');
            inp?.focus();
            inp?.addEventListener('input', e => { e.target.value = mascaraTel(e.target.value); });
            inp?.addEventListener('keypress', e => { if (e.key === 'Enter') enviarTelefone(); });
        }, 100);

    // ── Profissional — carrossel com foto ─────────────────────────────────────
    } else if (estado === 'profissional') {
        const cards = profissionais.map(p => {
            const fotoHtml = p.foto_url
                ? `<img src="${p.foto_url}" alt="${p.nome}" class="w-20 h-20 rounded-full object-cover">`
                : `<div class="w-20 h-20 rounded-full bg-amber-500 flex items-center justify-center text-white text-2xl font-bold">${p.nome.charAt(0).toUpperCase()}</div>`;
            return `
                <button onclick="escolherProfissional(${p.id}, '${p.nome.replace(/'/g, "\\'")}')"
                    data-prof="${p.id}"
                    class="prof-card flex-shrink-0 snap-start flex flex-col items-center gap-2 p-3 rounded-xl bg-gray-700 hover:bg-gray-600 border-2 border-transparent hover:border-amber-500 transition w-32 min-w-[8rem]">
                    ${fotoHtml}
                    <span class="text-white text-xs font-medium text-center leading-tight">${p.nome}</span>
                </button>
            `;
        }).join('');

        criarCard(`
            <div class="space-y-2">
                <div class="relative py-1">
                    <button onclick="scrollCarrossel('track-prof', -1)"
                        class="absolute left-0 top-1/2 -translate-y-1/2 z-10 w-8 h-8 bg-gray-600 hover:bg-amber-500 rounded-full flex items-center justify-center text-white text-xl font-bold transition">‹</button>
                    <div id="track-prof" class="carousel-track flex gap-3 overflow-x-auto snap-x snap-mandatory px-10 py-2">
                        ${cards}
                    </div>
                    <button onclick="scrollCarrossel('track-prof', 1)"
                        class="absolute right-0 top-1/2 -translate-y-1/2 z-10 w-8 h-8 bg-gray-600 hover:bg-amber-500 rounded-full flex items-center justify-center text-white text-xl font-bold transition">›</button>
                </div>
                ${btnVoltar('telefone')}
            </div>
        `);

    // ── Serviço — multi-seleção: marca 1 ou mais e clica em Continuar ────────
    } else if (estado === 'servico') {
        const selecionados = dadosCliente.servicos_sel || [];
        const cards = servicos.map(s => {
            const ativo = selecionados.includes(s.id);
            const fotoHtml = s.foto_url
                ? `<img src="${s.foto_url}" alt="${sanitizeText(s.nome)}" class="w-16 h-16 rounded-full object-cover">`
                : `<div class="w-16 h-16 rounded-full bg-amber-500 flex items-center justify-center text-2xl">✂️</div>`;
            return `
            <button onclick="toggleServico(${s.id})" data-serv="${s.id}"
                class="serv-card flex-shrink-0 snap-start flex flex-col items-center gap-2 p-3 rounded-xl hover:bg-gray-600 border-2 transition w-32 min-w-[8rem]
                    ${ativo ? 'border-amber-500 bg-gray-600' : 'border-transparent bg-gray-700'}">
                ${fotoHtml}
                <span class="text-white text-xs font-medium text-center leading-tight line-clamp-2 w-full"
                    title="${sanitizeText(s.nome)}">${sanitizeText(s.nome)}${s.destaque ? ' 🔥' : ''}</span>
                <span class="text-amber-400 text-xs font-semibold">R$ ${parseFloat(s.preco).toFixed(2).replace('.', ',')}</span>
                <span class="text-gray-400 text-xs">⏱ ${s.duracao_minutos}min</span>
            </button>`;
        }).join('');
        criarCard(`
            <div class="space-y-2">
                <div class="relative py-1">
                    <button onclick="scrollCarrossel('track-serv', -1)"
                        class="absolute left-0 top-1/2 -translate-y-1/2 z-10 w-8 h-8 bg-gray-600 hover:bg-amber-500 rounded-full flex items-center justify-center text-white text-xl font-bold transition">‹</button>
                    <div id="track-serv" class="carousel-track flex gap-3 overflow-x-auto snap-x snap-mandatory px-10 py-2">
                        ${cards}
                    </div>
                    <button onclick="scrollCarrossel('track-serv', 1)"
                        class="absolute right-0 top-1/2 -translate-y-1/2 z-10 w-8 h-8 bg-gray-600 hover:bg-amber-500 rounded-full flex items-center justify-center text-white text-xl font-bold transition">›</button>
                </div>
                <div id="serv-total" class="text-center text-white text-sm font-medium py-2 border border-gray-600 rounded-xl">
                    Toque nos serviços para selecionar
                </div>
                <button onclick="confirmarServicos()"
                    class="w-full bg-amber-500 hover:bg-amber-600 text-white rounded-2xl py-3 text-sm font-semibold transition">
                    Continuar ➜
                </button>
                ${btnVoltar('profissional')}
            </div>
        `);
        atualizarTotalServicos();

    // ── Data + Hora — card combinado ──────────────────────────────────────────
    } else if (estado === 'data') {
        const hoje = new Date();
        const nomeDias  = ['DOM', 'SEG', 'TER', 'QUA', 'QUI', 'SEX', 'SÁB'];
        const nomeMeses = ['JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ'];

        // Gera até 14 dias disponíveis conforme os dias de trabalho do barbeiro selecionado.
        // Começa em HOJE (offset 0) — a API filtra os horários já passados com buffer de 30min
        const diasTrabalho = dadosCliente.profissional_dias_trabalho ?? [1, 2, 3, 4, 5, 6];
        const dateCards = [];
        let offset = 0;
        while (dateCards.length < 14 && offset <= 60) {
            const d = new Date(hoje);
            d.setDate(hoje.getDate() + offset);
            if (diasTrabalho.includes(d.getDay())) {
                const dataStr = d.getFullYear() + '-'
                    + String(d.getMonth() + 1).padStart(2, '0') + '-'
                    + String(d.getDate()).padStart(2, '0');
                const sel = dadosCliente.data === dataStr;
                const rotuloDia = offset === 0 ? 'HOJE' : nomeDias[d.getDay()];
                dateCards.push(`
                    <button onclick="selecionarDataNoCard('${dataStr}')" data-data="${dataStr}"
                        class="data-card flex-shrink-0 snap-start flex flex-col items-center py-3 px-3 rounded-2xl border-2 transition min-w-[4.5rem]
                            ${sel ? 'bg-amber-600 border-amber-400' : 'bg-gray-700 border-transparent hover:bg-gray-600'}">
                        <span class="text-xs font-bold ${sel ? 'text-white' : (offset === 0 ? 'text-amber-400' : 'text-gray-400')}">${rotuloDia}</span>
                        <span class="text-2xl font-black text-white leading-tight">${d.getDate()}</span>
                        <span class="text-xs ${sel ? 'text-white' : 'text-gray-400'}">${nomeMeses[d.getMonth()]}</span>
                    </button>
                `);
            }
            offset++;
        }

        criarCard(`
            <div class="bg-gray-800 rounded-2xl p-4 space-y-4">
                <p class="text-gray-400 text-xs tracking-widest font-semibold">SELECIONE O DIA E HORÁRIO:</p>

                <div class="carousel-track flex gap-2 overflow-x-auto snap-x snap-mandatory" id="track-datas-card">
                    ${dateCards.join('')}
                </div>
                <p class="text-gray-500 text-xs text-center">→ ARRASTE PARA O LADO PARA VER MAIS</p>

                <div id="slots-container"></div>

                <div id="data-hora-display"
                    class="text-center text-white text-sm font-medium py-2 border border-gray-600 rounded-xl">
                    Selecione um dia e horário
                </div>

                <button onclick="confirmarDataHora()"
                    class="w-full bg-gray-600 hover:bg-amber-500 text-white rounded-2xl py-3 text-sm font-semibold transition">
                    Enviar
                </button>
                ${btnVoltar('servico')}
            </div>
        `);

        if (dadosCliente.data) selecionarDataNoCard(dadosCliente.data, false);

    // ── Campos extras ────────────────────────────────────────────────────────
    } else if (estado === 'campos_extras') {
        const campos = dadosCliente._camposExtras || [];
        let html = '<div id="campos-extras-form" class="space-y-3">';
        campos.forEach(c => {
            html += `<div>`;
            html += `<label class="block text-gray-300 text-sm mb-1">${sanitizeText(c.nome)}${c.obrigatorio ? ' <span class="text-red-400">*</span>' : ''}</label>`;
            if (c.tipo === 'select' && c.opcoes) {
                html += `<select data-campo="${c.slug}" class="w-full bg-gray-700 text-white rounded-xl px-4 py-3 text-sm border border-gray-600 focus:border-amber-500 focus:outline-none">`;
                html += `<option value="">Selecione...</option>`;
                c.opcoes.forEach(op => { html += `<option value="${sanitizeText(op)}">${sanitizeText(op)}</option>`; });
                html += `</select>`;
            } else if (c.tipo === 'toggle') {
                html += `<select data-campo="${c.slug}" class="w-full bg-gray-700 text-white rounded-xl px-4 py-3 text-sm border border-gray-600 focus:border-amber-500 focus:outline-none">`;
                html += `<option value="">Selecione...</option><option value="Sim">Sim</option><option value="Não">Não</option>`;
                html += `</select>`;
            } else {
                html += `<input data-campo="${c.slug}" type="text" class="w-full bg-gray-700 text-white rounded-xl px-4 py-3 text-sm border border-gray-600 focus:border-amber-500 focus:outline-none" placeholder="${sanitizeText(c.nome)}">`;
            }
            html += `</div>`;
        });
        html += `<button onclick="enviarCamposExtras()" class="w-full bg-amber-500 hover:bg-amber-600 text-white font-semibold py-3 rounded-2xl text-sm transition mt-2">Continuar ➜</button>`;
        html += btnVoltar('data');
        html += '</div>';
        criarCard(html);

    // ── Confirmar ─────────────────────────────────────────────────────────────
    } else if (estado === 'confirmar') {
        criarCard(`
            <div class="space-y-3">
                <div class="bg-gray-700 rounded-xl p-4 text-sm text-gray-300 space-y-2">
                    <div>👤 <strong class="text-white">${dadosCliente.nome}</strong></div>
                    <div>✂️ ${dadosCliente.servico_nome}</div>
                    <div>👨 ${dadosCliente.profissional_nome}</div>
                    <div>📅 ${formatarDataHora(dadosCliente.data_hora)}</div>
                    <div>💰 R$ ${parseFloat(dadosCliente.servico_preco).toFixed(2).replace('.', ',')}</div>
                    <div>⏱ ${dadosCliente.servico_duracao} min</div>
                    ${dadosCliente.dados_extras ? Object.entries(dadosCliente.dados_extras).filter(([,v])=>v).map(([k,v]) => `<div>📝 <strong>${sanitizeText(k)}</strong>: ${sanitizeText(v)}</div>`).join('') : ''}
                </div>
                <form method="POST" action="{{ route('agendamento.store', ['tenant' => $tenantSlug]) }}">
                    @csrf
                    <input type="hidden" name="cliente_nome" value="">
                    <input type="hidden" name="cliente_telefone" value="">
                    <input type="hidden" name="profissional_id" value="">
                    <input type="hidden" name="servico_id" value="">
                    <input type="hidden" name="servico_ids" value="">
                    <input type="hidden" name="data_hora" value="">
                    <input type="hidden" name="dados_extras" value="">
                    <button type="submit"
                        class="w-full bg-amber-500 hover:bg-amber-600 text-white font-semibold py-3 rounded-2xl text-sm transition">
                        ✅ Confirmar Agendamento
                    </button>
                </form>
                ${btnVoltar('data')}
            </div>
        `);
        const form = cardAtivo.querySelector('form');
        form.querySelector('[name=cliente_nome]').value     = dadosCliente.nome;
        form.querySelector('[name=cliente_telefone]').value = dadosCliente.telefone;
        form.querySelector('[name=profissional_id]').value  = dadosCliente.profissional_id;
        form.querySelector('[name=servico_id]').value       = dadosCliente.servico_id;
        form.querySelector('[name=servico_ids]').value      = dadosCliente.servico_ids || String(dadosCliente.servico_id);
        form.querySelector('[name=data_hora]').value        = dadosCliente.data_hora;
        form.querySelector('[name=dados_extras]').value     = JSON.stringify(dadosCliente.dados_extras || {});
    }
}

// ─── Ações do fluxo ───────────────────────────────────────────────────────────

function enviarNome() {
    const nome = document.getElementById('inp-nome')?.value.trim();
    if (!nome) return;
    addMensagemCliente(nome);
    dadosCliente.nome = nome;
    estado = 'telefone';
    addMensagemBot('Ótimo, <strong>' + sanitizeText(nome) + '</strong>! 😊<br>Agora me diga seu <strong>telefone</strong> (com DDD):');
    renderInput();
}

function enviarTelefone() {
    const tel = document.getElementById('inp-tel')?.value.trim();
    if (!tel || tel.replace(/\D/g, '').length < 10) {
        addMensagemBot('⚠️ Telefone inválido. Informe com DDD, ex: (11) 99999-9999');
        return;
    }
    addMensagemCliente(tel);
    dadosCliente.telefone = tel;

    fetch(`/${tenantSlug}/api/verificar-telefone`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ telefone: tel })
    })
    .then(r => r.json())
    .then(data => {
        // ── Mensalista Fixo ───────────────────────────────────────────────────
        if (data.tipo_cliente === 'mensalista_fixo') {
            if (data.proximos_horarios_fixos && data.proximos_horarios_fixos.length > 0) {
                mostrarModalMensalistaFixo(data);
            } else {
                // Sem horário fixo cadastrado ainda — fluxo normal
                carregarProfissionais();
            }
            return;
        }

        // ── Mensalista com limite semanal ─────────────────────────────────────
        if (data.tipo_cliente === 'mensalista') {
            if (data.limite_atingido) {
                addMensagemBot(
                    `⚠️ Você já utilizou <strong>${data.cortes_esta_semana}</strong> de ` +
                    `<strong>${data.limite_semana}</strong> corte(s) desta semana.<br>` +
                    `Tente novamente na próxima semana.`
                );
                criarCard(`
                    <form method="POST" action="{{ route('agendamento.meus-agendamentos', ['tenant' => $tenantSlug]) }}">
                        @csrf
                        <input type="hidden" name="telefone" value="${sanitizeText(tel)}">
                        <button type="submit"
                            class="w-full bg-amber-500 hover:bg-amber-600 text-white font-semibold py-3 rounded-2xl text-sm transition">
                            📋 Ver meus agendamentos
                        </button>
                    </form>
                `);
                return;
            }
            if (data.tem_agendamento) {
                mostrarModalAtivo(data.agendamento, tel);
                return;
            }
            carregarProfissionais();
            return;
        }

        // ── Avulso padrão ─────────────────────────────────────────────────────
        if (data.tem_agendamento) {
            mostrarModalAtivo(data.agendamento, tel);
        } else {
            carregarProfissionais();
        }
    })
    .catch(() => addMensagemBot('⚠️ Erro de conexão. Verifique sua internet e tente novamente.'));
}

function mostrarModalAtivo(ag, tel) {
    const dataFormatada = new Date(ag.data_hora).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
    document.getElementById('info-agendamento-ativo').innerHTML =
        `<strong>${sanitizeText(ag.servico?.nome || 'Serviço')}</strong> com <strong>${sanitizeText(ag.profissional?.nome || 'Profissional')}</strong><br>📅 ${dataFormatada}`;
    document.getElementById('input-telefone-modal').value = tel;
    document.getElementById('modal-agendamento-ativo').classList.remove('hidden');
}

function mostrarModalMensalistaFixo(data) {
    const lista = document.getElementById('lista-horarios-fixos');
    lista.innerHTML = data.proximos_horarios_fixos.map(h => `
        <div class="bg-gray-700 rounded-xl px-3 py-2 text-sm">
            <div class="text-white font-semibold">${h.dia_nome} ${h.data} às ${h.hora}</div>
            <div class="text-gray-400 text-xs">${h.servico} · ${h.profissional}</div>
        </div>
    `).join('');
    document.getElementById('modal-mensalista-fixo').classList.remove('hidden');
}

function fecharModalAtivo() {
    document.getElementById('modal-agendamento-ativo').classList.add('hidden');
    estado = 'telefone';
    renderInput();
}

function fecharModalFixo() {
    document.getElementById('modal-mensalista-fixo').classList.add('hidden');
}

// Mensalista fixo optou por agendar um horário avulso
function agendarAvulsoMensalistaFixo() {
    document.getElementById('modal-mensalista-fixo').classList.add('hidden');
    addMensagemBot('Ok! Vamos agendar um horário avulso para você. ✂️');
    carregarProfissionais();
}

function carregarProfissionais() {
    fetch(`/${tenantSlug}/api/profissionais`)
    .then(r => r.json())
    .then(data => {
        profissionais = data;
        estado = 'profissional';
        addMensagemBot('Perfeito! ✨ Com qual <strong>profissional</strong> você prefere ser atendido?');
        renderInput();
    })
    .catch(() => addMensagemBot('⚠️ Erro ao carregar profissionais. Recarregue a página.'));
}

function escolherProfissional(id, nome) {
    document.querySelectorAll('.prof-card').forEach(el => {
        el.classList.remove('border-amber-500');
        el.classList.add('border-transparent');
    });
    document.querySelector(`[data-prof="${id}"]`)?.classList.replace('border-transparent', 'border-amber-500');

    addMensagemCliente(nome);
    dadosCliente.profissional_id          = id;
    dadosCliente.profissional_nome        = nome;
    const prof = profissionais.find(p => p.id === id);
    dadosCliente.profissional_dias_trabalho = prof?.dias_trabalho ?? [1, 2, 3, 4, 5, 6];

    // Filtra pelos serviços que o profissional realiza + zera seleção anterior
    dadosCliente.servicos_sel = [];
    fetch(`/${tenantSlug}/api/servicos?profissional_id=${id}`)
    .then(r => r.json())
    .then(data => {
        servicos = data;
        estado = 'servico';
        addMensagemBot('Ótima escolha! ✂️ Quais <strong>serviços</strong> você deseja? Pode marcar mais de um!');
        renderInput();
    })
    .catch(() => addMensagemBot('⚠️ Erro ao carregar serviços. Tente novamente.'));
}

// ── Multi-seleção de serviços ────────────────────────────────────────────────

function toggleServico(id) {
    dadosCliente.servicos_sel = dadosCliente.servicos_sel || [];
    const idx = dadosCliente.servicos_sel.indexOf(id);
    if (idx >= 0) dadosCliente.servicos_sel.splice(idx, 1);
    else dadosCliente.servicos_sel.push(id);

    const card = document.querySelector(`[data-serv="${id}"]`);
    if (card) {
        const ativo = dadosCliente.servicos_sel.includes(id);
        card.classList.toggle('border-amber-500', ativo);
        card.classList.toggle('bg-gray-600', ativo);
        card.classList.toggle('border-transparent', !ativo);
        card.classList.toggle('bg-gray-700', !ativo);
    }
    atualizarTotalServicos();
}

function servicosSelecionadosLista() {
    return (dadosCliente.servicos_sel || [])
        .map(id => servicos.find(s => s.id === id))
        .filter(Boolean);
}

function atualizarTotalServicos() {
    const el = document.getElementById('serv-total');
    if (!el) return;
    const sel = servicosSelecionadosLista();
    if (!sel.length) {
        el.textContent = 'Toque nos serviços para selecionar';
        return;
    }
    const preco = sel.reduce((t, s) => t + parseFloat(s.preco), 0);
    const dur   = sel.reduce((t, s) => t + s.duracao_minutos, 0);
    el.innerHTML = `${sel.length} serviço${sel.length > 1 ? 's' : ''} — <strong class="text-amber-400">R$ ${preco.toFixed(2).replace('.', ',')}</strong> · ⏱ ${dur} min`;
}

function confirmarServicos() {
    const sel = servicosSelecionadosLista();
    if (!sel.length) {
        addMensagemBot('⚠️ Escolha pelo menos um serviço para continuar.');
        return;
    }

    const preco = sel.reduce((t, s) => t + parseFloat(s.preco), 0);
    dadosCliente.servico_id      = sel[0].id;
    dadosCliente.servico_ids     = sel.map(s => s.id).join(',');
    dadosCliente.servico_nome    = sel.map(s => s.nome).join(' + ');
    dadosCliente.servico_preco   = preco;
    dadosCliente.servico_duracao = sel.reduce((t, s) => t + s.duracao_minutos, 0);
    dadosCliente.data            = null;
    dadosCliente.hora            = null;
    dadosCliente.data_hora       = null;

    addMensagemCliente(dadosCliente.servico_nome + ' — R$ ' + preco.toFixed(2).replace('.', ','));
    estado = 'data';
    addMensagemBot('Combinado! 📅 Escolha o <strong>dia e horário</strong> do seu atendimento:');
    renderInput();
}

// Seleciona data no card combinado e carrega slots via API
function selecionarDataNoCard(dataStr, rolar = true) {
    document.querySelectorAll('.data-card').forEach(el => {
        el.classList.remove('bg-amber-600', 'border-amber-400');
        el.classList.add('bg-gray-700', 'border-transparent');
        el.querySelectorAll('span').forEach(s => {
            s.classList.remove('text-white');
            s.classList.add('text-gray-400');
        });
        el.querySelector('span:nth-child(2)')?.classList.replace('text-gray-400', 'text-white');
    });
    const dc = document.querySelector(`[data-data="${dataStr}"]`);
    if (dc) {
        dc.classList.remove('bg-gray-700', 'border-transparent');
        dc.classList.add('bg-amber-600', 'border-amber-400');
        dc.querySelectorAll('span').forEach(s => {
            s.classList.remove('text-gray-400');
            s.classList.add('text-white');
        });
    }

    dadosCliente.data      = dataStr;
    dadosCliente.hora      = null;
    dadosCliente.data_hora = null;

    const display = document.getElementById('data-hora-display');
    if (display) display.textContent = formatarDataSelecionada();

    const container = document.getElementById('slots-container');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-3 text-gray-400 text-sm animate-pulse">Carregando horários...</div>';

    const params = new URLSearchParams({
        profissional_id: dadosCliente.profissional_id,
        // Todos os serviços: a API soma as durações para achar slots que caibam
        servico_ids:     dadosCliente.servico_ids || String(dadosCliente.servico_id),
        data:            dataStr,
    });

    fetch(`/${tenantSlug}/api/horarios-disponiveis?${params}`)
    .then(r => r.json())
    .then(slots => {
        if (!Array.isArray(slots) || !slots.length) {
            let html = '<p class="text-center text-gray-400 text-sm py-2">Nenhum horário disponível nesse dia.</p>';
            // Oferece a lista de espera quando o estabelecimento tem o módulo
            if (temListaEspera) {
                html += `<button type="button" onclick="mostrarListaEspera('${dataStr}')"
                    class="w-full mt-1 bg-gray-700 hover:bg-amber-500 text-white rounded-2xl py-3 text-sm font-semibold transition border border-amber-500/40">
                    ✋ Entrar na lista de espera desse dia
                </button>`;
            }
            container.innerHTML = html;
            return;
        }
        const grid = slots.map(h =>
            `<button onclick="selecionarHoraNoCard('${h.hora}', '${h.datetime}')" data-hora="${h.hora}"
                class="hora-card bg-gray-700 hover:bg-amber-500 text-white rounded-2xl py-3 text-sm font-bold transition border-2 border-transparent">
                ${h.hora}
            </button>`
        ).join('');
        container.innerHTML = `<div class="grid grid-cols-3 gap-2">${grid}</div>`;
        if (rolar) container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    })
    .catch(() => {
        container.innerHTML = '<p class="text-center text-red-400 text-sm py-2">Erro ao carregar. Tente novamente.</p>';
    });
}

// ── Lista de espera ──────────────────────────────────────────────────────────
function mostrarListaEspera(dataStr) {
    const container = document.getElementById('slots-container');
    if (!container) return;
    horaEsperaSelecionada = null;
    const dataFmt = new Date(dataStr + 'T00:00').toLocaleDateString('pt-BR');
    container.innerHTML = `<div class="text-center py-3 text-gray-400 text-sm animate-pulse">Carregando horários...</div>`;

    // Busca a grade de horários que o profissional atende nesse dia
    fetch(`/${tenantSlug}/api/grade-horarios?profissional_id=${dadosCliente.profissional_id}&data=${dataStr}`)
    .then(r => r.json())
    .then(horarios => {
        const opcoes = (Array.isArray(horarios) ? horarios : [])
            .map(h => `<button type="button" onclick="selecionarHoraEspera(this, '${h}')" data-le-hora="${h}"
                class="le-hora-btn bg-gray-800 hover:bg-amber-500 text-white rounded-xl py-2.5 text-sm font-bold transition border-2 border-transparent">${h}</button>`)
            .join('');
        container.innerHTML = `
            <div class="bg-gray-700 rounded-2xl p-4 space-y-3">
                <p class="text-white text-sm">Escolha o horário que você gostaria para <strong>${dataFmt}</strong>. Se abrir vaga, entramos em contato.</p>
                <div class="grid grid-cols-3 gap-2">${opcoes || '<p class="col-span-3 text-gray-400 text-sm text-center">Sem horários configurados.</p>'}</div>
                <button type="button" onclick="enviarListaEspera('${dataStr}')"
                    class="w-full bg-amber-500 hover:bg-amber-600 text-white font-semibold py-3 rounded-2xl text-sm transition">
                    Entrar na lista de espera
                </button>
            </div>`;
        container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    })
    .catch(() => addMensagemBot('⚠️ Erro ao carregar horários. Tente novamente.'));
}

let horaEsperaSelecionada = null;
function selecionarHoraEspera(btn, hora) {
    document.querySelectorAll('.le-hora-btn').forEach(el => {
        el.classList.remove('bg-amber-500', 'border-amber-400');
        el.classList.add('bg-gray-800', 'border-transparent');
    });
    btn.classList.remove('bg-gray-800', 'border-transparent');
    btn.classList.add('bg-amber-500', 'border-amber-400');
    horaEsperaSelecionada = hora;
}

function enviarListaEspera(dataStr) {
    const hora = horaEsperaSelecionada;
    if (!hora) { addMensagemBot('⚠️ Escolha o horário que você gostaria.'); return; }

    fetch(`/${tenantSlug}/lista-espera`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        body: JSON.stringify({
            cliente_nome: dadosCliente.nome,
            cliente_telefone: dadosCliente.telefone,
            profissional_id: dadosCliente.profissional_id,
            servico_id: dadosCliente.servico_id,
            data: dataStr,
            hora_preferida: hora,
        }),
    })
    .then(r => { if (!r.ok) throw new Error(); return r.json(); })
    .then(() => {
        const dataFmt = new Date(dataStr + 'T00:00').toLocaleDateString('pt-BR');
        // Remove o card de horários e confirma a entrada na lista
        if (cardAtivo) cardAtivo.remove();
        addMensagemBot(`✅ Pronto, ${dadosCliente.nome}! Você entrou na lista de espera de <strong>${dataFmt}</strong> às <strong>${hora}</strong>. Se abrir vaga, entraremos em contato. 😊`);
    })
    .catch(() => addMensagemBot('⚠️ Não foi possível entrar na lista. Tente novamente.'));
}

function selecionarHoraNoCard(hora, datetime) {
    document.querySelectorAll('.hora-card').forEach(el => {
        el.classList.remove('bg-amber-500', 'border-amber-400');
        el.classList.add('bg-gray-700', 'border-transparent');
    });
    const card = document.querySelector(`[data-hora="${hora}"]`);
    if (card) {
        card.classList.remove('bg-gray-700', 'border-transparent');
        card.classList.add('bg-amber-500', 'border-amber-400');
    }
    dadosCliente.hora      = hora;
    dadosCliente.data_hora = datetime;
    const display = document.getElementById('data-hora-display');
    if (display) display.textContent = formatarDataSelecionada();
}

function confirmarDataHora() {
    if (!dadosCliente.data) { addMensagemBot('⚠️ Escolha um dia antes de continuar.'); return; }
    if (!dadosCliente.hora) { addMensagemBot('⚠️ Escolha um horário antes de continuar.'); return; }
    addMensagemCliente(formatarDataHora(dadosCliente.data_hora));

    // Verifica se tem campos extras configurados
    fetch(`/${tenantSlug}/api/campos-extras`)
    .then(r => r.json())
    .then(campos => {
        if (Array.isArray(campos) && campos.length > 0) {
            dadosCliente._camposExtras = campos;
            estado = 'campos_extras';
            addMensagemBot('📋 Precisamos de mais algumas informações:');
        } else {
            estado = 'confirmar';
            addMensagemBot('Quase lá! 🎉 Confirme os detalhes do seu agendamento:');
        }
        renderInput();
    })
    .catch(() => {
        estado = 'confirmar';
        addMensagemBot('Quase lá! 🎉 Confirme os detalhes do seu agendamento:');
        renderInput();
    });
}

function enviarCamposExtras() {
    const container = document.getElementById('campos-extras-form');
    if (!container) return;
    const extras = {};
    container.querySelectorAll('[data-campo]').forEach(el => {
        const slug = el.dataset.campo;
        extras[slug] = el.value;
    });
    // Validar obrigatórios
    for (const campo of (dadosCliente._camposExtras || [])) {
        if (campo.obrigatorio && !extras[campo.slug]) {
            addMensagemBot(`⚠️ O campo "${campo.nome}" é obrigatório.`);
            return;
        }
    }
    dadosCliente.dados_extras = extras;

    const resumo = Object.entries(extras).filter(([,v]) => v).map(([k,v]) => `${k}: ${v}`).join(', ');
    if (resumo) addMensagemCliente(resumo);

    estado = 'confirmar';
    addMensagemBot('Quase lá! 🎉 Confirme os detalhes do seu agendamento:');
    renderInput();
}

// Inicia o fluxo
renderInput();
</script>
@endpush
