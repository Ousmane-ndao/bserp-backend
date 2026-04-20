<?php

namespace Database\Seeders;

use App\Models\Destination;
use Illuminate\Database\Seeder;

class DestinationSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<int, array{name: string, region: string, type_compte: 'COMPLET'|'SIMPLE'}> */
        $rows = [
            // Europe
            ['name' => 'France', 'region' => 'Europe', 'type_compte' => 'COMPLET'],
            ['name' => 'Belgique', 'region' => 'Europe', 'type_compte' => 'SIMPLE'],
            ['name' => 'Suisse', 'region' => 'Europe', 'type_compte' => 'SIMPLE'],
            ['name' => 'Italie', 'region' => 'Europe', 'type_compte' => 'SIMPLE'],
            ['name' => 'Espagne', 'region' => 'Europe', 'type_compte' => 'SIMPLE'],
            ['name' => 'Allemagne', 'region' => 'Europe', 'type_compte' => 'SIMPLE'],
            ['name' => 'Royaume-Uni', 'region' => 'Europe', 'type_compte' => 'SIMPLE'],
            ['name' => 'Portugal', 'region' => 'Europe', 'type_compte' => 'SIMPLE'],
            ['name' => 'Pays-Bas', 'region' => 'Europe', 'type_compte' => 'SIMPLE'],
            ['name' => 'Irlande', 'region' => 'Europe', 'type_compte' => 'SIMPLE'],
            ['name' => 'Luxemburg', 'region' => 'Europe', 'type_compte' => 'SIMPLE'],
            // Asie
            ['name' => 'Chine', 'region' => 'Asie', 'type_compte' => 'SIMPLE'],
            ['name' => 'Japon', 'region' => 'Asie', 'type_compte' => 'SIMPLE'],
            ['name' => 'Corée du Sud', 'region' => 'Asie', 'type_compte' => 'SIMPLE'],
            ['name' => 'Vietnam', 'region' => 'Asie', 'type_compte' => 'SIMPLE'],
            ['name' => 'Thaïlande', 'region' => 'Asie', 'type_compte' => 'SIMPLE'],
            ['name' => 'Inde', 'region' => 'Asie', 'type_compte' => 'SIMPLE'],
            ['name' => 'Indonésie', 'region' => 'Asie', 'type_compte' => 'SIMPLE'],
            ['name' => 'Malaisie', 'region' => 'Asie', 'type_compte' => 'SIMPLE'],
            ['name' => 'Singapour', 'region' => 'Asie', 'type_compte' => 'SIMPLE'],
            ['name' => 'Turquie', 'region' => 'Asie', 'type_compte' => 'SIMPLE'],
            ['name' => 'Émirats arabes unis', 'region' => 'Asie', 'type_compte' => 'SIMPLE'],
            // Amérique
            ['name' => 'Canada', 'region' => 'Amérique', 'type_compte' => 'SIMPLE'],
            ['name' => 'États-Unis', 'region' => 'Amérique', 'type_compte' => 'SIMPLE'],
            ['name' => 'Mexique', 'region' => 'Amérique', 'type_compte' => 'SIMPLE'],
            ['name' => 'Brésil', 'region' => 'Amérique', 'type_compte' => 'SIMPLE'],
            ['name' => 'Argentine', 'region' => 'Amérique', 'type_compte' => 'SIMPLE'],
            ['name' => 'Chili', 'region' => 'Amérique', 'type_compte' => 'SIMPLE'],
            ['name' => 'Colombie', 'region' => 'Amérique', 'type_compte' => 'SIMPLE'],
            // Afrique
            ['name' => 'Maroc', 'region' => 'Afrique', 'type_compte' => 'SIMPLE'],
            ['name' => 'Sénégal', 'region' => 'Afrique', 'type_compte' => 'SIMPLE'],
            ['name' => 'Côte d\'Ivoire', 'region' => 'Afrique', 'type_compte' => 'SIMPLE'],
            ['name' => 'Tunisie', 'region' => 'Afrique', 'type_compte' => 'SIMPLE'],
            ['name' => 'Algérie', 'region' => 'Afrique', 'type_compte' => 'SIMPLE'],
            ['name' => 'Cameroun', 'region' => 'Afrique', 'type_compte' => 'SIMPLE'],
            ['name' => 'Gabon', 'region' => 'Afrique', 'type_compte' => 'SIMPLE'],
            ['name' => 'Mali', 'region' => 'Afrique', 'type_compte' => 'SIMPLE'],
        ];

        foreach ($rows as $row) {
            Destination::query()->updateOrCreate(
                ['name' => $row['name']],
                [
                    'region' => $row['region'],
                    'type_compte' => $row['type_compte'],
                ]
            );
        }
    }
}
