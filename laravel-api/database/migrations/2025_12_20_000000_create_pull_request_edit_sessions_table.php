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
        Schema::create('pull_request_edit_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pull_request_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('token', 32);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->foreign('pull_request_id')->references('id')->on('pull_requests')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['token']);
            $table->index(['pull_request_id']);
            $table->index(['user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pull_request_edit_sessions');
    }
}; 