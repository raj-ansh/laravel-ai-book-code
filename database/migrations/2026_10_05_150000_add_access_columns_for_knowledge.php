<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // Business customers belong to an organisation.
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organisation_id')->nullable()
                ->constrained();
        });

        Schema::table('knowledge_documents', function (Blueprint $table) {
            // public | staff | organisation
            $table->string('audience')->default('public');
            $table->foreignId('organisation_id')->nullable()
                ->constrained();
            $table->timestamp('flagged_at')->nullable();
            $table->string('flag_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organisation_id');
            $table->dropColumn(['audience', 'flagged_at', 'flag_reason']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organisation_id');
        });

        Schema::dropIfExists('organisations');
    }
};
