<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commercial_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('client_name')->nullable();
            $table->string('prospect_name')->nullable();
            $table->string('type');
            $table->date('date');
            $table->string('time')->nullable();
            $table->string('objective')->nullable();
            $table->string('result')->nullable();
            $table->text('commentary')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_activities');
    }
};
