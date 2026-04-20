<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Mots de passe d’initialisation fixes (démo / premier déploiement).
 * À faire changer en production après première connexion.
 */
class SecureUserSeeder extends Seeder
{
    /** @return array<string, string> email => mot de passe en clair (hashé à l’enregistrement via cast User) */
    private function initialPasswordsByEmail(): array
    {
        return [
            'admin@bserp.com' => 'BSERP-ADMIN-2026-X9K4',
            'mme.ba@bserp.com' => 'BSERP-BA-2026-M4D7',
            'mme.seck.admin@bserp.com' => 'BSERP-SECK-2026-Z8P3',
            'mme.barry@bserp.com' => 'BSERP-BARRY-2026-V2N9',
            'm.sane@bserp.com' => 'BSERP-SANE-2026-T7X5',
            'm.mbodj@bserp.com' => 'BSERP-MBODJ-2026-L6H8',
            'mme.diop.commercial@bserp.com' => 'BSERP-DIOP-COM-2026-B8S2',
            'm.gueye@bserp.com' => 'BSERP-GUEYE-2026-R3K6',
            'm.ndao@bserp.com' => 'BSERP-NDAO-2026-J9P4',
            'accueil.client@bserp.com' => 'BSERP-ACCUEIL-2026-D4F7',
        ];
    }

    public function run(): void
    {
        $map = $this->initialPasswordsByEmail();

        $lines = [
            'BSERP - Identifiants initialisation (mots de passe fixes)',
            'Genere le: '.now()->format('Y-m-d H:i:s'),
            str_repeat('-', 60),
        ];

        $employees = Employee::query()->orderBy('id')->get();

        foreach ($employees as $employee) {
            $email = $employee->email;
            if (! isset($map[$email])) {
                throw new \RuntimeException(
                    "SecureUserSeeder: aucun mot de passe défini pour l’employé « {$email} ». Complétez initialPasswordsByEmail()."
                );
            }

            $password = $map[$email];

            User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $employee->name,
                    'employee_id' => $employee->id,
                    'password' => $password,
                ]
            );

            $lines[] = "{$employee->name} | {$email} | {$password}";
        }

        $lines[] = str_repeat('-', 60);
        $lines[] = 'Important: changez ces mots de passe a la premiere connexion.';

        $directory = storage_path('app/secrets');
        File::ensureDirectoryExists($directory);
        File::put($directory.'/initial-user-passwords.txt', implode(PHP_EOL, $lines).PHP_EOL);
    }
}
