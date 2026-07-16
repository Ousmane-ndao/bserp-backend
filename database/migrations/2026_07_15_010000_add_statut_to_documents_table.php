<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasColumn('documents', 'statut')) {
                $table->string('statut', 32)->default('En attente')->after('type_document');
                $table->index('statut', 'documents_statut_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'statut')) {
                if ($this->indexExists('documents', 'documents_statut_index')) {
                    $table->dropIndex('documents_statut_index');
                }
                $table->dropColumn('statut');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $rows = $connection->select("PRAGMA index_list('{$table}')");

            return collect($rows)->contains(fn ($row) => ($row->name ?? null) === $index);
        }

        $database = $connection->getDatabaseName();

        return (bool) $connection->selectOne(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$database, $table, $index]
        );
    }
};
