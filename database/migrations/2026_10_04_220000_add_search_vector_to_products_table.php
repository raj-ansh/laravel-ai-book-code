<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Full-text search, kept up to date by PostgreSQL itself.
        Schema::table('products', function (Blueprint $table) {
            $table->tsvector('search_vector')->storedAs(
                "to_tsvector('english', coalesce(sku, '') || ' ' || "
                ."coalesce(name, '') || ' ' || coalesce(description, ''))"
            );

            $table->index('search_vector', algorithm: 'gin');
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('search_vector');
        });
    }
};
