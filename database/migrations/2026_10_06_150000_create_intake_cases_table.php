<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intake_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->text('message');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_mime')->nullable();
            $table->string('attachment_kind')->nullable();
            // What the attachment says, as text: a transcript, a
            // photo description or the document's text.
            $table->text('evidence')->nullable();
            $table->string('category')->nullable();
            $table->foreignId('order_id')->nullable()->constrained();
            $table->json('fields')->nullable();
            $table->json('issues')->nullable();
            // pending | open | needs_review | failed
            $table->string('status')->default('pending')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intake_cases');
    }
};
