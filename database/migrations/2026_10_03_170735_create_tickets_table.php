<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('subject');
            $table->text('body');

            // Filled in by AI triage (Chapter 10).
            $table->string('category')->nullable();
            $table->string('priority')->nullable();
            $table->boolean('requires_human')->default(true);
            $table->json('tags')->nullable();
            $table->string('summary')->nullable();
            $table->string('triaged_by')->nullable();
            $table->timestamp('triaged_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
