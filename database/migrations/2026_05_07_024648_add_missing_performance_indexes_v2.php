<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            if (! Schema::hasIndex('dossiers', 'dossiers_date_ouverture_index')) {
                $table->index('date_ouverture', 'dossiers_date_ouverture_index');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasIndex('invoices', 'invoices_statut_index')) {
                $table->index('statut', 'invoices_statut_index');
            }
            if (! Schema::hasIndex('invoices', 'invoices_date_emission_index')) {
                $table->index('date_emission', 'invoices_date_emission_index');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasIndex('payments', 'payments_date_paiement_index')) {
                $table->index('date_paiement', 'payments_date_paiement_index');
            }
        });

        Schema::table('expenses', function (Blueprint $table) {
            if (! Schema::hasIndex('expenses', 'expenses_date_depense_index')) {
                $table->index('date_depense', 'expenses_date_depense_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex('expenses_date_depense_index');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_date_paiement_index');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_statut_index');
            $table->dropIndex('invoices_date_emission_index');
        });
        Schema::table('dossiers', function (Blueprint $table) {
            $table->dropIndex('dossiers_date_ouverture_index');
        });
    }
};
