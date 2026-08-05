<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Agendamento') </title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Inter', sans-serif; }
        .chat-bubble-in {
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* iOS/Android dão zoom automático em inputs com fonte < 16px — trava em 16px no mobile */
        @media (max-width: 640px) {
            input, select, textarea { font-size: 16px !important; }
        }

        /* ── Tema claro (todas as páginas públicas): sobrepõe as cores escuras ── */
        .tema-claro, .tema-claro .bg-gray-900 { background-color: #f3f4f6 !important; }
        .tema-claro .bg-gray-800 { background-color: #ffffff !important; }
        .tema-claro .bg-gray-700 { background-color: #e9eaee !important; }
        .tema-claro .bg-gray-600 { background-color: #d7d9de !important; }
        .tema-claro .hover\:bg-gray-600:hover { background-color: #d7d9de !important; }
        .tema-claro .text-white { color: #111827 !important; }
        .tema-claro .text-gray-200, .tema-claro .text-gray-300 { color: #374151 !important; }
        .tema-claro .text-gray-400, .tema-claro .text-gray-500 { color: #6b7280 !important; }
        .tema-claro .border-gray-600, .tema-claro .border-gray-700 { border-color: #d1d5db !important; }
        .tema-claro .border-b { border-color: #e5e7eb !important; }
        .tema-claro .bg-amber-500 .text-white, .tema-claro .bg-amber-500.text-white { color: #111827 !important; }

        /* ── Tema Tecnológico (escuro + ciano) ───────────────────────────────── */
        .tema-tecnologico, .tema-tecnologico .bg-gray-900 { background-color: #0b1220 !important; }
        .tema-tecnologico .bg-gray-800 { background-color: #0f1c30 !important; }
        .tema-tecnologico .bg-gray-700 { background-color: #16263f !important; }
        .tema-tecnologico .bg-gray-600, .tema-tecnologico .bg-gray-500,
        .tema-tecnologico .hover\:bg-gray-600:hover, .tema-tecnologico .hover\:bg-gray-500:hover { background-color: #21395a !important; }
        .tema-tecnologico .text-gray-300 { color: #cbd5e1 !important; }
        .tema-tecnologico .text-gray-400, .tema-tecnologico .text-gray-500 { color: #7f94b3 !important; }
        .tema-tecnologico .border-gray-600, .tema-tecnologico .border-gray-700, .tema-tecnologico .border-b { border-color: #24344f !important; }
        .tema-tecnologico .bg-amber-500 { background-color: #06b6d4 !important; }
        .tema-tecnologico .bg-amber-600, .tema-tecnologico .hover\:bg-amber-600:hover { background-color: #0891b2 !important; }
        .tema-tecnologico .text-amber-400 { color: #38e0f0 !important; }
        .tema-tecnologico .border-amber-400, .tema-tecnologico .border-amber-500 { border-color: #22d3ee !important; }
        .tema-tecnologico .ring-amber-500, .tema-tecnologico .focus\:ring-amber-500:focus { --tw-ring-color: #06b6d4 !important; }
        .tema-tecnologico .focus\:border-amber-500:focus { border-color: #22d3ee !important; }
        .tema-tecnologico .bg-amber-500 .text-white, .tema-tecnologico .bg-amber-500.text-white { color: #052730 !important; }

        /* ── Tema Feminino (claro + rosa) ────────────────────────────────────── */
        .tema-feminino, .tema-feminino .bg-gray-900 { background-color: #fff1f6 !important; }
        .tema-feminino .bg-gray-800 { background-color: #ffffff !important; }
        .tema-feminino .bg-gray-700 { background-color: #fde7f0 !important; }
        .tema-feminino .bg-gray-600, .tema-feminino .bg-gray-500,
        .tema-feminino .hover\:bg-gray-600:hover, .tema-feminino .hover\:bg-gray-500:hover { background-color: #f9d3e4 !important; }
        .tema-feminino .text-white { color: #7a1e45 !important; }
        .tema-feminino .text-gray-300 { color: #9a4a6b !important; }
        .tema-feminino .text-gray-400, .tema-feminino .text-gray-500 { color: #a97389 !important; }
        .tema-feminino .text-green-400 { color: #16a34a !important; }
        .tema-feminino .border-gray-600, .tema-feminino .border-gray-700, .tema-feminino .border-b { border-color: #f3d0de !important; }
        .tema-feminino .bg-amber-500 { background-color: #ec4899 !important; }
        .tema-feminino .bg-amber-600, .tema-feminino .hover\:bg-amber-600:hover { background-color: #db2777 !important; }
        .tema-feminino .text-amber-400 { color: #db2777 !important; }
        .tema-feminino .border-amber-400, .tema-feminino .border-amber-500 { border-color: #f472b6 !important; }
        .tema-feminino .ring-amber-500, .tema-feminino .focus\:ring-amber-500:focus { --tw-ring-color: #ec4899 !important; }
        .tema-feminino .focus\:border-amber-500:focus { border-color: #ec4899 !important; }
        .tema-feminino .bg-amber-500 .text-white, .tema-feminino .bg-amber-500.text-white { color: #ffffff !important; }

        /* ── Tema Neutro (escuro + cinza) ────────────────────────────────────── */
        .tema-neutro, .tema-neutro .bg-gray-900 { background-color: #0f172a !important; }
        .tema-neutro .bg-gray-800 { background-color: #1b2536 !important; }
        .tema-neutro .bg-gray-700 { background-color: #2c3a4f !important; }
        .tema-neutro .bg-gray-600, .tema-neutro .bg-gray-500,
        .tema-neutro .hover\:bg-gray-600:hover, .tema-neutro .hover\:bg-gray-500:hover { background-color: #3b4a61 !important; }
        .tema-neutro .text-gray-300 { color: #cbd5e1 !important; }
        .tema-neutro .text-gray-400, .tema-neutro .text-gray-500 { color: #8b9bb0 !important; }
        .tema-neutro .border-gray-600, .tema-neutro .border-gray-700, .tema-neutro .border-b { border-color: #33415a !important; }
        .tema-neutro .bg-amber-500 { background-color: #64748b !important; }
        .tema-neutro .bg-amber-600, .tema-neutro .hover\:bg-amber-600:hover { background-color: #475569 !important; }
        .tema-neutro .text-amber-400 { color: #cbd5e1 !important; }
        .tema-neutro .border-amber-400, .tema-neutro .border-amber-500 { border-color: #64748b !important; }
        .tema-neutro .ring-amber-500, .tema-neutro .focus\:ring-amber-500:focus { --tw-ring-color: #64748b !important; }
        .tema-neutro .focus\:border-amber-500:focus { border-color: #64748b !important; }
        .tema-neutro .bg-amber-500 .text-white, .tema-neutro .bg-amber-500.text-white { color: #ffffff !important; }
    </style>
    @stack('styles')
</head>
<body class="bg-gray-900 min-h-screen tema-{{ $tema ?? 'escuro' }}">

    @yield('content')

    @stack('scripts')
</body>
</html>
