{{-- Ajustes finos de contraste no tema claro do painel.
     Os badges de status do Filament ficam pálidos demais no claro (verde/
     amarelo/vermelho quase brancos), prejudicando a leitura. Aqui reforçamos
     o fundo e escurecemos o texto — só no tema claro (html sem .dark). --}}
<style>
    html:not(.dark) .fi-badge { font-weight: 600; }

    html:not(.dark) .fi-badge.fi-color-success {
        background-color: #dcfce7; color: #15803d;
    }
    html:not(.dark) .fi-badge.fi-color-warning {
        background-color: #fef3c7; color: #b45309;
    }
    html:not(.dark) .fi-badge.fi-color-danger {
        background-color: #fee2e2; color: #b91c1c;
    }
    html:not(.dark) .fi-badge.fi-color-info {
        background-color: #dbeafe; color: #1d4ed8;
    }
</style>
