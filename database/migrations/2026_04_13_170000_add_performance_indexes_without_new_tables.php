<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dossiers')) {
            Schema::table('dossiers', function (Blueprint $table) {
                if (! $this->indexExists('dossiers', 'dossiers_client_id_index')) {
                    $table->index('client_id', 'dossiers_client_id_index');
                }
                if (! $this->indexExists('dossiers', 'dossiers_statut_index')) {
                    $table->index('statut', 'dossiers_statut_index');
                }
            });
        }

        if (Schema::hasTable('clients')) {
            Schema::table('clients', function (Blueprint $table) {
                if (! $this->indexExists('clients', 'clients_nom_index')) {
                    $table->index('nom', 'clients_nom_index');
                }
            });
        }

        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table) {
                if (! $this->indexExists('documents', 'documents_dossier_id_index')) {
                    $table->index('dossier_id', 'documents_dossier_id_index');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('clients')) {
            Schema::table('clients', function (Blueprint $table) {
                if ($this->indexExists('clients', 'clients_nom_index')) {
                    $table->dropIndex('clients_nom_index');
                }
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $conn = Schema::getConnection();
        if ($conn->getDriverName() === 'sqlite') {
            $rows = $conn->select("SELECT name FROM sqlite_master WHERE type = 'index' AND name = ?", [$index]);

            return count($rows) > 0;
        }

        $db = $conn->getDatabaseName();
        $rows = $conn->select(
            'SELECT COUNT(1) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$db, $table, $index]
        );

        return isset($rows[0]) && (int) $rows[0]->c > 0;
    }
};

