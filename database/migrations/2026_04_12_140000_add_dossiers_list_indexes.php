<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            $table->index('statut', 'dossiers_statut_index');
            $table->index('date_ouverture', 'dossiers_date_ouverture_index');
        });

        // destination_id sur clients est déjà indexé par la contrainte FK (migration ERP).
    }

    public function down(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            $table->dropIndex('dossiers_statut_index');
            $table->dropIndex('dossiers_date_ouverture_index');
        });
    }
};
