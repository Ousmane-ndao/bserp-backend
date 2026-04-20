<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Les mots de passe étaient stockés en hash (bcrypt) : ils ne peuvent pas être affichés en clair.
 * Passage au chiffrement réversible : on efface les anciennes valeurs (à saisir à nouveau).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_accounts')) {
            return;
        }

        DB::table('student_accounts')->update([
            'email_password' => null,
            'campus_password' => null,
            'parcoursup_password' => null,
        ]);
    }

    public function down(): void
    {
        // Irréversible : on ne restaure pas d'anciens hash.
    }
};
