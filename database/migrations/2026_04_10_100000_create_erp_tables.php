<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('telephone')->nullable();
            $table->string('statut', 16)->default('Actif');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });

        Schema::create('destinations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->enum('type_compte', ['COMPLET', 'SIMPLE'])->default('SIMPLE');
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('prenom');
            $table->string('nom');
            $table->date('date_naissance')->nullable();
            $table->string('etablissement')->nullable();
            $table->string('niveau_etude')->nullable();
            $table->string('telephone')->nullable();
            $table->string('email');
            $table->foreignId('destination_id')->constrained('destinations')->cascadeOnUpdate()->restrictOnDelete();
            $table->date('date_ouverture')->nullable();
            $table->timestamps();
        });

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->unique()->constrained('clients')->cascadeOnDelete();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('campus_password')->nullable();
            $table->string('parcoursup_password')->nullable();
            $table->timestamps();
        });

        Schema::create('dossiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->string('type')->nullable();
            $table->string('statut', 32)->default('En cours');
            $table->date('date_ouverture')->nullable();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('dossier_id')->nullable()->constrained('dossiers')->nullOnDelete();
            $table->string('type_document');
            $table->string('file_path');
            $table->string('original_filename')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('mime')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->decimal('montant', 12, 2);
            $table->string('methode');
            $table->date('date_paiement');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
        });

        Schema::dropIfExists('payments');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('dossiers');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('destinations');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('roles');
    }
};
