<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every answer the platform gives: what was asked, what
        // was said, which sources back it, how it was produced,
        // and whether the person found it useful.
        Schema::create('knowledge_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('organisation_id')->nullable()
                ->constrained();
            $table->string('conversation_id', 36)->nullable()->index();
            $table->text('question');
            $table->text('reply');
            // [{"document_id": 1, "title": "...", "quote": "..."}]
            $table->json('citations');
            // agent | fallback | none
            $table->string('route');
            // What the agent searched for, to debug misses.
            $table->json('searches')->nullable();
            $table->string('trace_id')->nullable();
            $table->boolean('helpful')->nullable();
            $table->string('feedback', 500)->nullable();
            $table->timestamps();
            $table->index(['helpful', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_answers');
    }
};
