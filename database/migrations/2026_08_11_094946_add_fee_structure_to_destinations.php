<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            // Ajout des champs pour la structure tarifaire
            $table->decimal('frais_accompagnement', 10, 2)->nullable()->after('montant_total');
            $table->decimal('frais_campus_france', 10, 2)->nullable()->after('frais_accompagnement');
            $table->decimal('frais_visa', 10, 2)->nullable()->after('frais_campus_france');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            // Suppression des champs si on rollback la migration
            $table->dropColumn(['frais_accompagnement', 'frais_campus_france', 'frais_visa']);
        });
    }
};