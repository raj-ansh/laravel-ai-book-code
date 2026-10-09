<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('source')->unique();
            $table->char('hash', 40);
            $table->timestamps();
        });

        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_document_id')
                ->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->text('content');
            $table->timestamps();
        });

        // The vector column needs pgvector (Chapter 28).
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::ensureVectorExtensionExists();

            Schema::table('knowledge_chunks', function (Blueprint $table) {
                $table->vector(
                    'embedding', config('acme.ai.embeddings.dimensions'),
                )->nullable();
                $table->vectorIndex('embedding');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_chunks');
        Schema::dropIfExists('knowledge_documents');
    }
};
