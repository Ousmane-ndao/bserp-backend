<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Devise principale de l'application (ISO 4217)
    |--------------------------------------------------------------------------
    */
    'code' => env('APP_CURRENCY_CODE', 'XOF'),

    /*
    |--------------------------------------------------------------------------
    | Libellé affiché (ex. FCFA pour le franc CFA BCEAO)
    |--------------------------------------------------------------------------
    */
    'label' => env('APP_CURRENCY_LABEL', 'FCFA'),

    /*
    |--------------------------------------------------------------------------
    | Taux indicatif MAD → XOF (pour migration / conversion ponctuelle)
    |--------------------------------------------------------------------------
    | À ajuster selon le taux du jour (Banque centrale / opérateur).
    | Utilisé uniquement par la migration si CONVERT_LEGACY_MAD est true.
    */
    'mad_to_xof_rate' => (float) env('CURRENCY_MAD_TO_XOF_RATE', 60.0),

];
