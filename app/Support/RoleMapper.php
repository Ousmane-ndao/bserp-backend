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
        if ($dbName === null || trim($dbName) === '') {
            return null;
        }

        $normalized = mb_strtolower(trim($dbName));
        if (isset(self::KEY_TO_DB[$normalized])) {
            return $normalized;
        }

        foreach (self::KEY_TO_DB as $key => $label) {
            if (mb_strtolower($label) === $normalized) {
                return $key;
            }
        }

        return null;
    }
}
