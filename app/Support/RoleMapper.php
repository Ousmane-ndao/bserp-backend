<?php

namespace App\Support;

class RoleMapper
{
    public const KEY_TO_DB = [
        'directrice' => 'Directrice',
        'responsable_admin' => 'Responsable administrative',
        'conseillere_pedagogique' => 'Conseillère pédagogique',
        'informaticien' => 'Informaticien',
        'comptable' => 'Comptable',
        'commercial' => 'Commercial',
        'accueil' => 'Accueil client',
    ];

    public static function toDbName(string $key): string
    {
        return self::KEY_TO_DB[$key] ?? self::KEY_TO_DB['accueil'];
    }

    public static function toFrontendKey(?string $dbName): ?string
    {
        if ($dbName === null || $dbName === '') {
            return null;
        }

        $flip = array_flip(self::KEY_TO_DB);

        return $flip[$dbName] ?? null;
    }
}
