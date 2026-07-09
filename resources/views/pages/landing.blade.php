<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atendix — Sistema de Agendamento Inteligente</title>
    <meta name="description" content="Sistema completo de agendamento online com WhatsApp, relatórios e painel do profissional. Pra barbearias, salões, clínicas e mais.">
    <link rel="icon" type="image/png" href="{{ asset('images/logo-atendix.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .gradient-text { background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .glow { box-shadow: 0 0 80px rgba(245, 158, 11, 0.12); }
        .float { animation: float 6s ease-in-out infinite; }
        @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-10px)} }
        .fade-in { animation: fadeIn 0.8s ease-out; }
        @keyframes fadeIn { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
        .hero-grid { background-image: radial-gradient(rgba(245,158,11,0.07) 1px, transparent 1px); background-size: 40px 40px; }
    </style>
</head>
<body class="bg-gray-950 text-white font-sans antialiased overflow-x-hidden">

    {{-- NAV --}}
    <nav class="fixed top-0 w-full z-50 bg-gray-950/80 backdrop-blur-xl border-b border-white/5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-brand-500 rounded-lg flex items-center justify-center text-black font-black text-sm">A</div>
                <span class="font-bold text-lg">tendix</span>
            </div>
            <div class="hidden lg:flex items-center gap-8 text-sm text-gray-400">
                <a href="#features" class="hover:text-white transition">Recursos</a>
                <a href="#como-funciona" class="hover:text-white transition">Como funciona</a>
                <a href="#tipos" class="hover:text-white transition">Segmentos</a>
                <a href="#planos" class="hover:text-white transition">Planos</a>
            </div>
            <div class="flex items-center gap-3">
                <a href="/admin/login" class="hidden sm:inline-block text-sm text-gray-400 hover:text-white transition">Entrar</a>
                <a href="https://wa.me/5514998382598?text=Quero%20conhecer%20o%20Atendix" target="_blank"
                   class="bg-brand-500 hover:bg-brand-600 text-black font-semibold px-4 py-2 rounded-xl text-sm transition">
                    Testar grátis
                </a>
            </div>
        </div>
    </nav>

    {{-- HERO --}}
    <section class="relative pt-24 sm:pt-32 lg:pt-40 pb-16 lg:pb-28 px-4 sm:px-6 hero-grid">
        <div class="max-w-7xl mx-auto">
            <div class="lg:grid lg:grid-cols-2 lg:gap-16 lg:items-center">
                {{-- Texto --}}
                <div class="fade-in text-center lg:text-left">
                    <div class="inline-block bg-brand-500/10 text-brand-400 text-xs font-semibold px-4 py-1.5 rounded-full mb-6 border border-brand-500/20">
                        ✨ Agendamento inteligente para o seu negócio
                    </div>
                    <h1 class="text-4xl sm:text-5xl lg:text-6xl xl:text-7xl font-black leading-tight mb-6">
                        Seus clientes<br class="hidden sm:block"> agendam.
                        <span class="gradient-text">Você fatura mais.</span>
                    </h1>
                    <p class="text-base sm:text-lg lg:text-xl text-gray-400 max-w-xl mx-auto lg:mx-0 mb-8 leading-relaxed">
                        Sistema completo de agendamento com WhatsApp, relatórios financeiros
                        e painel do profissional. Pra barbearias, salões, clínicas e muito mais.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-3 justify-center lg:justify-start">
                        <a href="https://wa.me/5514998382598?text=Quero%20conhecer%20o%20Atendix" target="_blank"
                           class="bg-brand-500 hover:bg-brand-600 text-black font-bold px-6 sm:px-8 py-3.5 sm:py-4 rounded-2xl text-base sm:text-lg transition transform hover:scale-105 glow text-center">
                            Quero testar grátis →
                        </a>
                        <a href="#features"
                           class="bg-white/5 hover:bg-white/10 text-white font-semibold px-6 sm:px-8 py-3.5 sm:py-4 rounded-2xl text-base sm:text-lg transition border border-white/10 text-center">
                            Ver recursos
                        </a>
                    </div>
                </div>

                {{-- Mockup (desktop) --}}
                <div class="hidden lg:block relative mt-12 lg:mt-0">
                    <div class="relative">
                        {{-- Phone mockup --}}
                        <div class="w-72 mx-auto bg-gray-900 rounded-3xl p-3 border border-white/10 glow float">
                            <div class="bg-gray-800 rounded-2xl overflow-hidden">
                                <div class="bg-gray-700 px-4 py-3 flex items-center gap-2">
                                    <div class="w-8 h-8 bg-brand-500 rounded-full flex items-center justify-center text-black text-xs font-bold">A</div>
                                    <div>
                                        <div class="text-white text-xs font-semibold">Atendix</div>
                                        <div class="text-green-400 text-[10px]">● Online</div>
                                    </div>
                                </div>
                                <div class="p-3 space-y-2">
                                    <div class="bg-gray-700 rounded-xl rounded-tl-none px-3 py-2 text-xs text-gray-200 max-w-[85%]">
                                        Olá! 👋 Vamos agendar seu horário?
                                    </div>
                                    <div class="bg-brand-500 rounded-xl rounded-tr-none px-3 py-2 text-xs text-black max-w-[70%] ml-auto">
                                        Quero agendar!
                                    </div>
                                    <div class="bg-gray-700 rounded-xl rounded-tl-none px-3 py-2 text-xs text-gray-200 max-w-[85%]">
                                        Ótimo! Escolha o profissional: 💈
                                    </div>
                                    <div class="grid grid-cols-2 gap-1.5">
                                        <div class="bg-brand-500/20 border border-brand-500 rounded-lg p-2 text-center">
                                            <div class="text-[10px] text-brand-300 font-semibold">Rafael</div>
                                        </div>
                                        <div class="bg-gray-700 border border-transparent rounded-lg p-2 text-center">
                                            <div class="text-[10px] text-gray-400">Lucas</div>
                                        </div>
                                    </div>
                                    <div class="bg-gray-700 rounded-xl rounded-tl-none px-3 py-2 text-xs text-gray-200 max-w-[85%]">
                                        Escolha o horário: 📅
                                    </div>
                                    <div class="grid grid-cols-3 gap-1">
                                        <div class="bg-gray-600 rounded-lg py-1.5 text-center text-[10px] text-white font-bold">09:00</div>
                                        <div class="bg-brand-500 rounded-lg py-1.5 text-center text-[10px] text-black font-bold">10:00</div>
                                        <div class="bg-gray-600 rounded-lg py-1.5 text-center text-[10px] text-white font-bold">11:00</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{-- Badge --}}
                        <div class="absolute -bottom-4 -right-4 bg-green-500/20 border border-green-500/30 rounded-2xl px-4 py-2 backdrop-blur">
                            <div class="text-green-400 text-xs font-bold">✅ Confirmado!</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- STATS --}}
    <section class="py-10 sm:py-12 border-y border-white/5">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 grid grid-cols-2 lg:grid-cols-4 gap-6 sm:gap-8 text-center">
            <div>
                <div class="text-2xl sm:text-3xl font-black text-brand-400">24/7</div>
                <div class="text-xs sm:text-sm text-gray-500 mt-1">Agendamento online</div>
            </div>
            <div>
                <div class="text-2xl sm:text-3xl font-black text-brand-400">-80%</div>
                <div class="text-xs sm:text-sm text-gray-500 mt-1">Menos no-show</div>
            </div>
            <div>
                <div class="text-2xl sm:text-3xl font-black text-brand-400">+40%</div>
                <div class="text-xs sm:text-sm text-gray-500 mt-1">Mais agendamentos</div>
            </div>
            <div>
                <div class="text-2xl sm:text-3xl font-black text-brand-400">0</div>
                <div class="text-xs sm:text-sm text-gray-500 mt-1">Papel e caneta</div>
            </div>
        </div>
    </section>

    {{-- COMO FUNCIONA --}}
    <section id="como-funciona" class="py-16 sm:py-24 px-4 sm:px-6">
        <div class="max-w-6xl mx-auto">
            <div class="text-center mb-12 sm:mb-16">
                <h2 class="text-3xl sm:text-4xl font-black mb-4">Como funciona</h2>
                <p class="text-gray-400 text-base sm:text-lg">3 passos. Sem complicação.</p>
            </div>
            <div class="grid sm:grid-cols-3 gap-6 sm:gap-8">
                <div class="text-center">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 bg-brand-500/20 rounded-2xl flex items-center justify-center mx-auto mb-4 text-2xl sm:text-3xl">1️⃣</div>
                    <h3 class="font-bold text-lg mb-2">Cadastramos seu negócio</h3>
                    <p class="text-gray-400 text-sm">Configuramos tudo: profissionais, serviços, horários e WhatsApp. Em minutos.</p>
                </div>
                <div class="text-center">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 bg-brand-500/20 rounded-2xl flex items-center justify-center mx-auto mb-4 text-2xl sm:text-3xl">2️⃣</div>
                    <h3 class="font-bold text-lg mb-2">Compartilhe o link</h3>
                    <p class="text-gray-400 text-sm">Seus clientes acessam o link e agendam pelo celular. Sem baixar nada.</p>
                </div>
                <div class="text-center">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 bg-brand-500/20 rounded-2xl flex items-center justify-center mx-auto mb-4 text-2xl sm:text-3xl">3️⃣</div>
                    <h3 class="font-bold text-lg mb-2">Gerencie tudo no painel</h3>
                    <p class="text-gray-400 text-sm">Agenda, financeiro, comissões, WhatsApp automático. Tudo num só lugar.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- FEATURES --}}
    <section id="features" class="py-16 sm:py-24 px-4 sm:px-6 bg-white/[0.02]">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-12 sm:mb-16">
                <h2 class="text-3xl sm:text-4xl font-black mb-4">Tudo que você precisa</h2>
                <p class="text-gray-400 text-base sm:text-lg">Num único sistema.</p>
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                @php
                $features = [
                    ['icon' => '📱', 'title' => 'Agendamento por Chat', 'desc' => 'Seus clientes agendam pelo celular num fluxo simples estilo WhatsApp. Sem app, sem cadastro.'],
                    ['icon' => '💬', 'title' => 'WhatsApp Integrado', 'desc' => 'Confirmação automática, lembretes e cancelamento por resposta. Tudo pelo WhatsApp.'],
                    ['icon' => '📊', 'title' => 'Relatórios Completos', 'desc' => 'Faturamento, comissões, ticket médio, taxa de cancelamento. Exporta PDF e Excel.'],
                    ['icon' => '👨‍💼', 'title' => 'Painel do Profissional', 'desc' => 'Cada profissional vê sua agenda visual, receita, comissão e agenda direto pelo celular.'],
                    ['icon' => '🔄', 'title' => 'Repescagem de Clientes', 'desc' => 'Identifica clientes sumidos e envia mensagem de volta pelo WhatsApp automaticamente.'],
                    ['icon' => '⏰', 'title' => 'Cancelamento Inteligente', 'desc' => 'Não confirmou? O sistema cancela e libera o horário. Avisa o profissional.'],
                    ['icon' => '💰', 'title' => 'Salário Emocional', 'desc' => 'Distribui receita de mensalidades proporcionalmente entre os profissionais.'],
                    ['icon' => '🏢', 'title' => 'Multi-estabelecimento', 'desc' => 'Gerencie várias unidades. Cada uma com seu link, dados e configurações.'],
                    ['icon' => '🎨', 'title' => 'Totalmente Customizável', 'desc' => 'Campos personalizados, logo, intervalos, módulos por plano. Seu sistema, sua cara.'],
                ];
                @endphp
                @foreach($features as $f)
                <div class="bg-white/5 rounded-2xl p-5 sm:p-6 border border-white/5 hover:border-brand-500/30 transition group">
                    <div class="text-2xl sm:text-3xl mb-3">{{ $f['icon'] }}</div>
                    <h3 class="text-base sm:text-lg font-bold mb-2 group-hover:text-brand-400 transition">{{ $f['title'] }}</h3>
                    <p class="text-gray-400 text-xs sm:text-sm leading-relaxed">{{ $f['desc'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- SEGMENTOS --}}
    <section id="tipos" class="py-16 sm:py-24 px-4 sm:px-6">
        <div class="max-w-5xl mx-auto text-center">
            <h2 class="text-3xl sm:text-4xl font-black mb-4">Pra todo tipo de negócio</h2>
            <p class="text-gray-400 text-base sm:text-lg mb-10 sm:mb-12">Qualquer negócio que trabalha com horário.</p>
            <div class="grid grid-cols-3 sm:grid-cols-5 gap-3 sm:gap-4">
                @php
                $tipos = [
                    ['icon' => '💈', 'nome' => 'Barbearias'],
                    ['icon' => '💇', 'nome' => 'Salões'],
                    ['icon' => '🏥', 'nome' => 'Clínicas'],
                    ['icon' => '🐾', 'nome' => 'Pet Shops'],
                    ['icon' => '🎨', 'nome' => 'Studios'],
                ];
                @endphp
                @foreach($tipos as $t)
                <div class="bg-white/5 rounded-2xl p-4 sm:p-6 border border-white/5 text-center hover:border-brand-500/30 transition">
                    <div class="text-3xl sm:text-4xl mb-2">{{ $t['icon'] }}</div>
                    <div class="font-semibold text-xs sm:text-sm">{{ $t['nome'] }}</div>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- PLANOS --}}
    <section id="planos" class="py-16 sm:py-24 px-4 sm:px-6 bg-white/[0.02]">
        <div class="max-w-6xl mx-auto">
            <div class="text-center mb-12 sm:mb-16">
                <h2 class="text-3xl sm:text-4xl font-black mb-4">Planos</h2>
                <p class="text-gray-400 text-base sm:text-lg">Comece grátis. Escale quando quiser.</p>
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                {{-- Starter --}}
                <div class="bg-white/5 rounded-2xl p-6 sm:p-8 border border-white/10">
                    <h3 class="font-bold text-lg mb-1">Starter</h3>
                    <div class="text-2xl sm:text-3xl font-black mb-1">R$ 97<span class="text-sm sm:text-base font-normal text-gray-500">/mês</span></div>
                    <p class="text-gray-500 text-xs sm:text-sm mb-6">Pra começar a organizar</p>
                    <ul class="space-y-2.5 text-xs sm:text-sm text-gray-300 mb-8">
                        <li>✅ Agendamento online</li>
                        <li>✅ Painel administrativo</li>
                        <li>✅ Gestão de mensalistas</li>
                        <li>✅ Indisponibilidades</li>
                        <li class="text-gray-600">✕ WhatsApp</li>
                        <li class="text-gray-600">✕ Relatórios</li>
                    </ul>
                    <a href="https://wa.me/5514998382598?text=Quero%20o%20plano%20Starter" target="_blank"
                       class="block text-center bg-white/10 hover:bg-white/20 py-3 rounded-xl font-semibold text-sm transition">
                        Começar agora
                    </a>
                </div>

                {{-- Pro --}}
                <div class="bg-brand-500/10 rounded-2xl p-6 sm:p-8 border-2 border-brand-500 relative">
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-brand-500 text-black text-[10px] sm:text-xs font-bold px-3 sm:px-4 py-1 rounded-full whitespace-nowrap">
                        MAIS POPULAR
                    </div>
                    <h3 class="font-bold text-lg mb-1">Pro</h3>
                    <div class="text-2xl sm:text-3xl font-black mb-1">R$ 197<span class="text-sm sm:text-base font-normal text-gray-500">/mês</span></div>
                    <p class="text-gray-500 text-xs sm:text-sm mb-6">Pra crescer com inteligência</p>
                    <ul class="space-y-2.5 text-xs sm:text-sm text-gray-300 mb-8">
                        <li>✅ Tudo do Starter</li>
                        <li>✅ Integração WhatsApp</li>
                        <li>✅ Relatórios + PDF/Excel</li>
                        <li>✅ Salário Emocional</li>
                        <li>✅ Painel do profissional</li>
                        <li class="text-gray-600">✕ Repescagem</li>
                    </ul>
                    <a href="https://wa.me/5514998382598?text=Quero%20o%20plano%20Pro" target="_blank"
                       class="block text-center bg-brand-500 hover:bg-brand-600 text-black py-3 rounded-xl font-bold text-sm transition">
                        Quero o Pro →
                    </a>
                </div>

                {{-- Enterprise --}}
                <div class="bg-white/5 rounded-2xl p-6 sm:p-8 border border-white/10 sm:col-span-2 lg:col-span-1">
                    <h3 class="font-bold text-lg mb-1">Enterprise</h3>
                    <div class="text-2xl sm:text-3xl font-black mb-1">R$ 397<span class="text-sm sm:text-base font-normal text-gray-500">/mês</span></div>
                    <p class="text-gray-500 text-xs sm:text-sm mb-6">Tudo liberado, sem limites</p>
                    <ul class="space-y-2.5 text-xs sm:text-sm text-gray-300 mb-8">
                        <li>✅ Tudo do Pro</li>
                        <li>✅ Repescagem de clientes</li>
                        <li>✅ Campos personalizados</li>
                        <li>✅ Import/Export</li>
                        <li>✅ Suporte prioritário</li>
                        <li>✅ Multi-estabelecimento</li>
                    </ul>
                    <a href="https://wa.me/5514998382598?text=Quero%20o%20plano%20Enterprise" target="_blank"
                       class="block text-center bg-white/10 hover:bg-white/20 py-3 rounded-xl font-semibold text-sm transition">
                        Falar com consultor
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="py-16 sm:py-24 px-4 sm:px-6">
        <div class="max-w-3xl mx-auto text-center bg-gradient-to-br from-brand-500/20 to-brand-600/5 rounded-3xl p-8 sm:p-12 border border-brand-500/20 glow">
            <h2 class="text-3xl sm:text-4xl font-black mb-4">Pronto pra automatizar?</h2>
            <p class="text-gray-400 text-base sm:text-lg mb-6 sm:mb-8">Teste grátis por 7 dias. Sem cartão de crédito.</p>
            <a href="https://wa.me/5514998382598?text=Quero%20testar%20o%20Atendix%20grátis" target="_blank"
               class="inline-block bg-brand-500 hover:bg-brand-600 text-black font-bold px-8 sm:px-10 py-3.5 sm:py-4 rounded-2xl text-base sm:text-lg transition transform hover:scale-105">
                Começar agora →
            </a>
        </div>
    </section>

    {{-- FOOTER --}}
    <footer class="py-8 sm:py-12 px-4 sm:px-6 border-t border-white/5">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <div class="w-6 h-6 bg-brand-500 rounded-md flex items-center justify-center text-black font-black text-xs">A</div>
                <span class="font-bold">tendix</span>
                <span class="text-gray-600 text-xs sm:text-sm ml-2">Agendamento inteligente</span>
            </div>
            <div class="text-gray-600 text-xs sm:text-sm">
                © {{ date('Y') }} Atendix. Todos os direitos reservados.
            </div>
        </div>
    </footer>

</body>
</html>
