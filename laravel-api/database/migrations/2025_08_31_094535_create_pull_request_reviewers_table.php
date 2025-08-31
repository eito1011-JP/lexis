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
        Schema::create('pull_request_reviewers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pull_request_id');
            $table->foreignId('user_id');
            $table->string('action_status')->default('pending');
            $table->timestamps();
            $table->foreign('pull_request_id')->references('id')->on('pull_requests')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pull_request_reviewers');
    }
};
