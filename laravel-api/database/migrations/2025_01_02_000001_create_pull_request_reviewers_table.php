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
            $table->foreignId('pull_request_id')->constrained('pull_requests')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            // 同じプルリクエストに同じユーザーが複数回レビュアーとして追加されないようにする
            $table->unique(['pull_request_id', 'user_id']);
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
