<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'Directrice',
            'Responsable administrative',
            'Conseillère pédagogique',
            'Informaticien',
            'Comptable',
            'Commercial',
            'Accueil client',
        ];

        foreach ($roles as $name) {
            Role::query()->firstOrCreate(['name' => $name]);
        }
    }
}
