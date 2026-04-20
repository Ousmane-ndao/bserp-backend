<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Role;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // Compte technique admin (email de connexion distinct des autres employés)
            ['name' => 'Administrateur BSERP', 'email' => 'admin@bserp.com', 'role' => 'Informaticien'],
            ['name' => 'Mme Ba', 'email' => 'mme.ba@bserp.com', 'role' => 'Directrice'],
            ['name' => 'Mme Seck', 'email' => 'mme.seck.admin@bserp.com', 'role' => 'Responsable administrative'],
            ['name' => 'Mme Barry', 'email' => 'mme.barry@bserp.com', 'role' => 'Conseillère pédagogique'],
            ['name' => 'M. Sane', 'email' => 'm.sane@bserp.com', 'role' => 'Comptable'],
            ['name' => 'M. Mbodj', 'email' => 'm.mbodj@bserp.com', 'role' => 'Commercial'],
            ['name' => 'Mme Diop', 'email' => 'mme.diop.commercial@bserp.com', 'role' => 'Commercial'],
            ['name' => 'M. Gueye', 'email' => 'm.gueye@bserp.com', 'role' => 'Informaticien'],
            ['name' => 'M. Ndao', 'email' => 'm.ndao@bserp.com', 'role' => 'Informaticien'],
            ['name' => 'Accueil Client', 'email' => 'accueil.client@bserp.com', 'role' => 'Accueil client'],
        ];

        foreach ($rows as $row) {
            $role = Role::query()->where('name', $row['role'])->first();
            if (! $role) {
                continue;
            }

            Employee::query()->firstOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'role_id' => $role->id,
                    'telephone' => null,
                    'statut' => 'Actif',
                ]
            );
        }
    }
}
