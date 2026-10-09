<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per evaluation run: which suite, which model,
        // which version of the prompt, and how it scored.
        Schema::create('eval_runs', function (Blueprint $table) {
            $table->id();
            $table->string('suite');
            $table->string('model');
            $table->string('prompt_hash', 12);
            $table->unsignedInteger('cases');
            $table->unsignedInteger('passed');
            $table->float('score');
            $table->unsignedInteger('duration_ms');
            $table->timestamps();
        });

        Schema::create('eval_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eval_run_id')->constrained()
                ->cascadeOnDelete();
            $table->string('case_id');
            $table->boolean('passed');
            $table->float('score');
            $table->json('output')->nullable();
            $table->json('failed_checks');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eval_results');
        Schema::dropIfExists('eval_runs');
    }
};
