<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('seolful_seo_pages', function (Blueprint $table) {
            $table->id();
            $table->string('url')->unique();
            $table->string('slug', 500)->nullable();
            $table->string('title', 255)->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('h1', 255)->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->json('image_alts')->nullable();       // [{src, alt, missing: bool}]
            $table->unsignedInteger('internal_link_count')->default(0);
            $table->json('structured_data')->nullable();  // array of JSON-LD objects
            $table->boolean('noindex')->default(false);
            $table->string('canonical_url')->nullable();
            $table->timestamp('crawled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seolful_seo_pages');
    }
};
