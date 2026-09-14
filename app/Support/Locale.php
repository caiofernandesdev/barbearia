<?php

namespace App\Support;

use App\Models\ConfiguracaoBarbearia;

/**
 * Idioma do estabelecimento. O dono fixa em Configurações; público e painel
 * passam a exibir naquele idioma. Fonte da verdade: ConfiguracaoBarbearia.idioma.
 */
class Locale
{
    /** @var list<string> */
    public const SUPORTADOS = ['pt_BR', 'en', 'es', 'it'];

    public const PADRAO = 'pt_BR';

    /** Rótulos para o seletor no painel. */
    public static function opcoes(): array
    {
        return [
            'pt_BR' => '🇧🇷 Português',
            'en' => '🇺🇸 English',
            'es' => '🇪🇸 Español',
            'it' => '🇮🇹 Italiano',
        ];
    }

    /** Aplica o idioma configurado pelo tenant atual (chamado nos middlewares). */
    public static function aplicarDoTenant(): void
    {
        if (! app()->bound('current_tenant')) {
            return;
        }

        $idioma = ConfiguracaoBarbearia::getInstance()->idioma ?? self::PADRAO;

        app()->setLocale(
            in_array($idioma, self::SUPORTADOS, true) ? $idioma : self::PADRAO
        );
    }
}
