<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute la colonne devise (XOF) et, si activé, convertit les montants
     * supposés être en MAD vers FCFA (voir .env / config/currency.php).
     */
    public function up(): void
    {
        $addedColumn = false;

        if (! Schema::hasColumn('payments', 'currency')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('currency', 8)->default('XOF')->after('montant');
            });
            $addedColumn = true;
        }

        // Ne convertir que si la colonne vient d’être créée (anciens montants = MAD implicite).
        // Si la colonne existait déjà (ex. import SQL), ne pas multiplier les montants.
        if (! $addedColumn) {
            return;
        }

        $convert = filter_var(env('CURRENCY_CONVERT_LEGACY_MAD', true), FILTER_VALIDATE_BOOLEAN);
        $rate = (float) config('currency.mad_to_xof_rate', 60.0);

        if ($convert && $rate > 0 && $rate !== 1.0) {
            DB::table('payments')->orderBy('id')->chunkById(100, function ($rows) use ($rate) {
                foreach ($rows as $row) {
                    DB::table('payments')->where('id', $row->id)->update([
                        'montant' => round((float) $row->montant * $rate, 2),
                        'currency' => 'XOF',
                        'updated_at' => now(),
                    ]);
                }
            });
        } else {
            DB::table('payments')->update(['currency' => 'XOF']);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payments', 'currency')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('currency');
            });
        }
    }
};
