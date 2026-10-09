<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            // description | seo_title | meta_description | social_post
            $table->string('kind');
            $table->text('body');
            $table->json('issues');
            // draft -> approved | rejected
            $table->string('status')->default('draft');
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('decided_by')->nullable()
                ->constrained('users');
            $table->timestamps();
        });

        // Published content lives beside the facts, never over them.
        Schema::table('products', function (Blueprint $table) {
            $table->text('marketing_copy')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('meta_description')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'marketing_copy', 'seo_title', 'meta_description',
            ]);
        });

        Schema::dropIfExists('content_drafts');
    }
};
