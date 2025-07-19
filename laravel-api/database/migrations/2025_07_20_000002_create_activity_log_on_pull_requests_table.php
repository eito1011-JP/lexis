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
        Schema::create('activity_log_on_pull_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('pull_request_id')->constrained('pull_requests')->onDelete('cascade');
            $table->foreignId('comment_id')->nullable()->constrained('comments')->onDelete('cascade');
            $table->foreignId('fix_request_id')->nullable()->constrained('fix_requests')->onDelete('cascade');
            $table->foreignId('reviewer_id')->nullable()->constrained('pull_request_reviewers')->onDelete('cascade');
            $table->string('action'); // プルリクエスト作成、編集、修正リクエスト送信、レビュアー設定、承認等
            $table->timestamps();

            // インデックスを追加
            $table->index(['pull_request_id', 'created_at']);
            $table->index(['user_id', 'action']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_log_on_pull_requests');
    }
};
