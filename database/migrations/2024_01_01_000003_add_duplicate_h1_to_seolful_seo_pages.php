<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('seolful_seo_pages', function (Blueprint $table) {
            $table->unsignedTinyInteger('h1_count')->default(0)->after('h1');
            $table->string('h1_secondary', 255)->nullable()->after('h1_count');
            $table->boolean('demote_h1')->default(false)->after('h1_secondary');
        });
    }

    public function down(): void
    {
        Schema::table('seolful_seo_pages', function (Blueprint $table) {
            $table->dropColumn(['h1_count', 'h1_secondary', 'demote_h1']);
        });
    }
};
