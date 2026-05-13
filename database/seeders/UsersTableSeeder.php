<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    public function run()
    {
        $users = [
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

        foreach ($users as $email => $plainPassword) {
            // Récupérer l'id de l'employé correspondant
            $employee = DB::table('employees')->where('email', $email)->first();

            if (!$employee) {
                $this->command->error("Employé introuvable pour l'email : $email");
                continue;
            }

            $name = explode('@', $email)[0];

            DB::table('users')->updateOrInsert(
                ['email' => $email],
                [
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make($plainPassword),
                    'employee_id' => $employee->id, // Liaison avec l'employé
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('Utilisateurs créés/mis à jour avec succès.');
    }
}
