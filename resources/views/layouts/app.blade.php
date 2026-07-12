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
    </style>
    @stack('styles')
</head>
<body class="bg-gray-900 min-h-screen {{ ($tema ?? 'escuro') === 'claro' ? 'tema-claro' : '' }}">

    @yield('content')

    @stack('scripts')
</body>
</html>
