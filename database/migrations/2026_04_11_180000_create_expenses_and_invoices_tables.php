<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('libelle');
            $table->decimal('montant', 12, 2);
            $table->string('currency', 8)->default('XOF');
            $table->string('categorie')->nullable();
            $table->date('date_depense');
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('numero', 32)->unique();
            $table->date('date_emission');
            $table->date('date_echeance')->nullable();
            $table->string('statut', 24)->default('brouillon');
            $table->decimal('montant_ttc', 12, 2);
            $table->string('currency', 8)->default('XOF');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('expenses');
    }
};
