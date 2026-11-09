#!/usr/bin/env bash
# Deploy do Atendix na VPS.
#
# Quem chama: o GitHub Actions (via SSH, depois que os testes passam na main)
# ou você, à mão:  bash /var/www/barbearia/scripts/deploy.sh
#
# Só roda o que a mudança pede (composer, build do front, migrations) e sempre
# recompila os caches, reinicia os workers e limpa o OPcache.
#
# Tudo fica dentro de main(): o bash lê a função inteira antes de executar,
# então o git pull pode atualizar este próprio arquivo sem quebrar o deploy.

main() {
    set -Eeuo pipefail
    cd /var/www/barbearia

    # Um deploy por vez
    exec 9>/tmp/deploy-barbearia.lock
    flock -n 9 || { echo "Já existe um deploy do Atendix rodando."; exit 1; }

    local antes depois mudou
    antes=$(git rev-parse HEAD)

    php artisan down --retry=15 || true
    trap 'echo ">> ERRO no deploy. Site voltando ao ar; confira a mensagem acima."; php artisan up' ERR

    git pull --ff-only origin main
    depois=$(git rev-parse HEAD)
    mudou=$(git diff --name-only "$antes" "$depois")
    echo ">> ${antes:0:7} -> ${depois:0:7}"

    if grep -qE '^composer\.(json|lock)$' <<<"$mudou"; then
        echo ">> composer install"
        composer install --no-dev --optimize-autoloader --no-interaction
    fi

    if grep -qE '^(package(-lock)?\.json|vite\.config\.js|resources/(css|js)/)' <<<"$mudou"; then
        echo ">> build do front"
        npm ci && npm run build
    fi

    if grep -qE '^database/migrations/' <<<"$mudou"; then
        echo ">> migrations"
        php artisan migrate --force
    fi

    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
    php artisan filament:cache-components

    chown -R www-data:www-data storage bootstrap/cache   # artisan como root deixa arquivos do root → 500
    supervisorctl restart 'barbearia-worker:*'
    systemctl reload php8.3-fpm

    php artisan up
    trap - ERR
    echo ">> Deploy OK: $(git log -1 --format='%h %s')"
}

main "$@"
