<?php

return [
    /*
     * Chaves VAPID — identificam o servidor perante o serviço de push do
     * navegador. Geradas uma única vez com `php artisan push:vapid`.
     * Sem elas configuradas, o push simplesmente não é oferecido.
     */
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', config('app.url')),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],
];
