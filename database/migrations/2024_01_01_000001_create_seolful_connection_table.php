<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('seolful_connection', function (Blueprint $table) {
            $table->id();
            $table->string('client_id', 32)->unique();
            $table->string('token_hash');
            $table->string('site_url');
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seolful_connection');
    }
};
