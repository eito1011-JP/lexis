<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('hashed_refresh_token');
            $table->timestamp('expired_at');
            $table->boolean('is_blacklisted')->default(false);
            $table->timestamp('blacklisted_at')->nullable();
            $table->timestamps();
        });
    }
};
