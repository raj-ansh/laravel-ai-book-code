<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_documents', function (Blueprint $table) {
            // pending -> extracting -> embedding -> ready | failed
            $table->string('status')->default('ready');
            $table->string('path')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedInteger('chunk_count')->default(0);
            $table->text('error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_documents', function (Blueprint $table) {
            $table->dropColumn([
                'status', 'path', 'mime', 'chunk_count', 'error',
            ]);
        });
    }
};
