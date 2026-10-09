<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('reference')->unique();
            $table->json('lines');
            $table->unsignedInteger('subtotal_cents');
            $table->unsignedInteger('discount_cents');
            $table->unsignedInteger('total_cents');
            $table->date('valid_until');

            // The tool call that created it: retries are no-ops.
            $table->string('tool_call_id')->nullable()->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
