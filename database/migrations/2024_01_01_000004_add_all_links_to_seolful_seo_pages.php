<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('seolful_seo_pages', function (Blueprint $table) {
            $table->json('all_links')->nullable()->after('internal_link_count');
        });
    }

    public function down(): void
    {
        Schema::table('seolful_seo_pages', function (Blueprint $table) {
            $table->dropColumn('all_links');
        });
    }
};
