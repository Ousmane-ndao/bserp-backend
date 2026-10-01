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

    public const ROLE_PERMISSIONS = [
        'directrice' => ['dashboard', 'commercial', 'clients', 'dossiers', 'documents', 'comptabilite', 'personnel', 'mon_dossier'],
        'responsable_admin' => ['dashboard', 'commercial', 'clients', 'dossiers', 'documents', 'comptabilite', 'personnel', 'mon_dossier'],
        'conseillere_pedagogique' => ['dashboard', 'clients', 'dossiers', 'documents', 'mon_dossier'],
        'informaticien' => ['dashboard', 'commercial', 'clients', 'dossiers', 'documents', 'comptabilite', 'personnel', 'mon_dossier'],
        'comptable' => ['dashboard', 'clients', 'dossiers', 'documents', 'comptabilite', 'mon_dossier'],
        'commercial' => ['dashboard', 'commercial', 'clients', 'dossiers', 'mon_dossier'],
        'accueil' => ['dashboard', 'clients', 'dossiers', 'mon_dossier'],
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

    /**
     * @return list<string>
     */
    public static function permissionsForRole(?string $roleName): array
    {
        if ($roleName === null || trim($roleName) === '') {
            return [];
        }

        $normalized = mb_strtolower(trim($roleName));

        if (isset(self::ROLE_PERMISSIONS[$normalized])) {
            return self::ROLE_PERMISSIONS[$normalized];
        }

        $frontendKey = self::toFrontendKey($roleName);
        if ($frontendKey !== null && isset(self::ROLE_PERMISSIONS[$frontendKey])) {
            return self::ROLE_PERMISSIONS[$frontendKey];
        }

        return [];
    }
}
