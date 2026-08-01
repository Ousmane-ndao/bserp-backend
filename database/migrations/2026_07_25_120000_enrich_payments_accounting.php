<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('destinations', 'montant_total')) {
            Schema::table('destinations', function (Blueprint $table) {
                $table->decimal('montant_total', 15, 2)->default(272500.00)->after('type_compte');
            });
        }

        if (! Schema::hasColumn('payments', 'dossier_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->foreignId('dossier_id')->nullable()->after('client_id')->constrained('dossiers')->nullOnDelete();
            });
        } elseif (! $this->indexExists('payments', 'payments_dossier_id_index')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->index('dossier_id', 'payments_dossier_id_index');
            });
        }

        if (! Schema::hasColumn('payments', 'avance_numero')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('avance_numero', 10)->nullable()->after('methode');
            });
        }

        if (! Schema::hasColumn('payments', 'commentaire')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->text('commentaire')->nullable()->after('avance_numero');
            });
        }

        if (! Schema::hasTable('payment_audit_logs')) {
            Schema::create('payment_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
                $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 20);
                $table->json('payload')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        $this->backfillDossierIds();
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_audit_logs');

        if (Schema::hasColumn('payments', 'dossier_id')) {
            Schema::table('payments', function (Blueprint $table) {
                if ($this->indexExists('payments', 'payments_dossier_id_index')) {
                    $table->dropIndex('payments_dossier_id_index');
                }
                $table->dropConstrainedForeignId('dossier_id');
            });
        }

        if (Schema::hasColumn('payments', 'avance_numero') || Schema::hasColumn('payments', 'commentaire')) {
            Schema::table('payments', function (Blueprint $table) {
                $columns = array_filter([
                    Schema::hasColumn('payments', 'avance_numero') ? 'avance_numero' : null,
                    Schema::hasColumn('payments', 'commentaire') ? 'commentaire' : null,
                ]);
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasColumn('destinations', 'montant_total')) {
            Schema::table('destinations', function (Blueprint $table) {
                $table->dropColumn('montant_total');
            });
        }
    }

    private function backfillDossierIds(): void
    {
        DB::table('payments')
            ->whereNull('dossier_id')
            ->orderBy('id')
            ->chunk(200, function ($payments): void {
                foreach ($payments as $payment) {
                    $dossierId = DB::table('dossiers')
                        ->where('client_id', $payment->client_id)
                        ->orderBy('id')
                        ->value('id');

                    if ($dossierId) {
                        DB::table('payments')
                            ->where('id', $payment->id)
                            ->update(['dossier_id' => $dossierId]);
                    }
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
