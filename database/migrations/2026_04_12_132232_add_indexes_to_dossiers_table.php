<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            if (! $this->indexExists('dossiers', 'dossiers_reference_index')) {
                $table->index('reference', 'dossiers_reference_index');
            }
            if (! $this->indexExists('dossiers', 'dossiers_client_id_index')) {
                $table->index('client_id', 'dossiers_client_id_index');
            }
        });

        // `destination_id` est sur la table `clients` (jointure dossiers → clients → destinations).
        Schema::table('clients', function (Blueprint $table) {
            if (! $this->indexExists('clients', 'clients_destination_id_index')) {
                $table->index('destination_id', 'clients_destination_id_index');
            }
        });

        if (Schema::getConnection()->getDriverName() === 'mysql' && ! $this->indexExists('dossiers', 'dossiers_reference_fulltext')) {
            DB::statement('ALTER TABLE dossiers ADD FULLTEXT dossiers_reference_fulltext (reference)');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql' && $this->indexExists('dossiers', 'dossiers_reference_fulltext')) {
            DB::statement('ALTER TABLE dossiers DROP INDEX dossiers_reference_fulltext');
        }

        Schema::table('clients', function (Blueprint $table) {
            if ($this->indexExists('clients', 'clients_destination_id_index')) {
                $table->dropIndex('clients_destination_id_index');
            }
        });

        Schema::table('dossiers', function (Blueprint $table) {
            if ($this->indexExists('dossiers', 'dossiers_reference_index')) {
                $table->dropIndex('dossiers_reference_index');
            }
            if ($this->indexExists('dossiers', 'dossiers_client_id_index')) {
                $table->dropIndex('dossiers_client_id_index');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return Schema::hasIndex($table, $index);
    }
};
