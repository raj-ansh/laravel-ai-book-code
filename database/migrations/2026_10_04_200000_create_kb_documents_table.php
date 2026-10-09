<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which handbook files are in the provider's store.
        Schema::create('kb_documents', function (Blueprint $table) {
            $table->id();
            $table->string('path')->unique();
            $table->char('hash', 40);
            $table->string('file_id');
            $table->string('document_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_documents');
    }
};
