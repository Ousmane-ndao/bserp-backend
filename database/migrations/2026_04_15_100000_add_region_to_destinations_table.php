<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            $table->string('region', 64)->nullable()->after('name');
        });

        if (Schema::hasTable('destinations')) {
            $map = [
                'France' => 'Europe',
                'Canada' => 'Amérique',
                'Maroc' => 'Afrique',
                'Turquie' => 'Asie',
            ];
            foreach ($map as $name => $region) {
                DB::table('destinations')->where('name', $name)->update(['region' => $region]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            $table->dropColumn('region');
        });
    }
};
