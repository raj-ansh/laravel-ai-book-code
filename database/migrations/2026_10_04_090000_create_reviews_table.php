<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->unsignedTinyInteger('rating');
            $table->text('body');
            $table->string('topic')->nullable();
            $table->unsignedTinyInteger('sentiment')->nullable();
            $table->boolean('needs_reply')->nullable();
            $table->string('classified_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
