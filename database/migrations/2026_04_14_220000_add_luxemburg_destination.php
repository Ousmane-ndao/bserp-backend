<?php

use App\Models\Destination;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ajouter la colonne `region` si elle n'existe pas
        if (!Schema::hasColumn('destinations', 'region')) {
            Schema::table('destinations', function (Blueprint $table) {
                $table->string('region', 64)->nullable()->after('name');
            });
        }

        // Insérer Luxemburg
        Destination::query()->updateOrCreate(
            ['name' => 'Luxemburg'],
            ['region' => 'Europe', 'type_compte' => 'SIMPLE']
        );
    }

    public function down(): void
    {
        Destination::query()->where('name', 'Luxemburg')->delete();
    }
};

