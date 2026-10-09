<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per agent run, model step or tool call, linked
        // into a tree by parent_id. Timings and counts, no text.
        Schema::create('ai_spans', function (Blueprint $table) {
            $table->id();
            $table->string('trace_id')->index();
            $table->string('span_id');
            $table->string('parent_id')->nullable();
            $table->string('kind'); // agent | step | tool
            $table->string('name');
            $table->string('status'); // ok | error
            $table->float('duration_ms');
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_spans');
    }
};
