<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * Gera o par de chaves VAPID usado pelo push do navegador.
 * Roda uma vez por ambiente; o resultado vai para o .env.
 */
class GerarChavesVapid extends Command
{
    protected $signature = 'push:vapid';

    protected $description = 'Gera as chaves VAPID para as notificações push';

    public function handle(): int
    {
        if (config('webpush.vapid.public_key')) {
            $this->warn('Já existem chaves VAPID configuradas neste ambiente.');
            $this->line('Gerar novas invalida as inscrições atuais — todo mundo teria que autorizar de novo.');

            if (! $this->confirm('Gerar mesmo assim?', false)) {
                return self::SUCCESS;
            }
        }

        // Dependência ausente é o caso mais comum e tem solução própria —
        // não faz sentido mandar o usuário investigar o openssl por isso
        if (! class_exists(VAPID::class)) {
            $this->error('A biblioteca de push não está instalada.');
            $this->line('Rode:  composer install --no-dev --optimize-autoloader');

            return self::FAILURE;
        }

        try {
            $chaves = VAPID::createVapidKeys();
        } catch (\Throwable $e) {
            $this->error('Não foi possível gerar as chaves: '.$e->getMessage());
            $this->line('Verifique a extensão openssl do PHP (no Windows, também o OPENSSL_CONF).');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Chaves geradas. Cole no .env do servidor:');
        $this->newLine();
        $this->line('VAPID_SUBJECT='.config('app.url'));
        $this->line('VAPID_PUBLIC_KEY='.$chaves['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$chaves['privateKey']);
        $this->newLine();
        $this->comment('Depois: php artisan config:cache');

        return self::SUCCESS;
    }
}
