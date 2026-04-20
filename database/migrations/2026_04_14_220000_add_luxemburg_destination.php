<?php

use App\Models\Destination;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
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

