<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (! $this->indexExists('documents', 'documents_client_id_index')) {
                $table->index('client_id', 'documents_client_id_index');
            }
            if (! $this->indexExists('documents', 'documents_dossier_id_index')) {
                $table->index('dossier_id', 'documents_dossier_id_index');
            }
            if (! $this->indexExists('documents', 'documents_type_document_index')) {
                $table->index('type_document', 'documents_type_document_index');
            }
            if (! $this->indexExists('documents', 'documents_created_at_index')) {
                $table->index('created_at', 'documents_created_at_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if ($this->indexExists('documents', 'documents_client_id_index')) {
                $table->dropIndex('documents_client_id_index');
            }
            if ($this->indexExists('documents', 'documents_dossier_id_index')) {
                $table->dropIndex('documents_dossier_id_index');
            }
            if ($this->indexExists('documents', 'documents_type_document_index')) {
                $table->dropIndex('documents_type_document_index');
            }
            if ($this->indexExists('documents', 'documents_created_at_index')) {
                $table->dropIndex('documents_created_at_index');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return Schema::hasIndex($table, $index);
    }
};
