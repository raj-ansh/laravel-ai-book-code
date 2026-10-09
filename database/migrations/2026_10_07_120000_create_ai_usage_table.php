<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per model call (agent step): who, what, how
        // many tokens and what it cost. Never the content.
        Schema::create('ai_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->foreignId('organisation_id')->nullable()
                ->constrained();
            $table->string('feature');
            $table->string('provider');
            $table->string('model');
            $table->unsignedInteger('input_tokens');
            $table->unsignedInteger('output_tokens');
            // Millionths of a US dollar: exact integer sums.
            $table->unsignedBigInteger('cost_micros');
            $table->string('invocation_id')->index();
            $table->timestamp('created_at')->index();
            $table->index(['organisation_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage');
    }
};
