<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Vector columns need PostgreSQL (pgvector) or MariaDB.
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::ensureVectorExtensionExists();

        Schema::table('products', function (Blueprint $table) {
            $table->vector(
                'embedding', config('acme.ai.embeddings.dimensions'),
            )->nullable();
            $table->string('embedding_model')->nullable();
            $table->char('embedding_hash', 40)->nullable();

            $table->vectorIndex('embedding');
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'embedding', 'embedding_model', 'embedding_hash',
            ]);
        });
    }
};
