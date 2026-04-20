<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Inscription ouverte (API POST /register)
    |--------------------------------------------------------------------------
    |
    | Par défaut : désactivé. Les comptes se créent via le back-office / seeders.
    | Mettre à true uniquement en environnement contrôlé si besoin.
    |
    */

    'allow_open_registration' => filter_var(env('APP_ALLOW_OPEN_REGISTRATION', false), FILTER_VALIDATE_BOOL),

];
