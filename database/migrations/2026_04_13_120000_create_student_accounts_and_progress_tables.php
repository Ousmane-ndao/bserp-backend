<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->unique()->constrained('clients')->cascadeOnDelete();
            $table->string('email', 150)->nullable();
            $table->string('email_password')->nullable();
            $table->string('campus_password')->nullable();
            $table->string('parcoursup_password')->nullable();
            $table->timestamps();
        });

        Schema::create('student_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->unique()->constrained('clients')->cascadeOnDelete();
            $table->boolean('lettre_motivation')->default(false);
            $table->boolean('bulletins_enregistres')->default(false);
            $table->boolean('travail_effectue')->default(false);
            $table->boolean('notes_saisies')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_progress');
        Schema::dropIfExists('student_accounts');
    }
};
