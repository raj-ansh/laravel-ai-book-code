<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            // none | basic | pro: which AI features it has.
            $table->string('ai_plan')->default('none');
            // Per-tenant choices from fixed lists: model, tone.
            $table->json('ai_settings')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn(['ai_plan', 'ai_settings']);
        });
    }
};
