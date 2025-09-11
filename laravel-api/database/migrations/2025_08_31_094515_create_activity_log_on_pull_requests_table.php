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
            $table->foreignId('user_id');
            $table->foreignId('pull_request_id');
            $table->foreignId('comment_id')->nullable();
            $table->foreignId('reviewer_id')->nullable();
            $table->foreignId('pull_request_edit_session_id')->nullable();
            $table->string('action');
            $table->string('fix_request_token')->nullable();
            $table->string('old_pull_request_title')->nullable();
            $table->string('new_pull_request_title')->nullable();
            $table->timestamps();
            // 短い制約名を指定
            $table->foreign('user_id', 'fk_activity_log_pr_user_id')
                ->references('id')->on('users')->onDelete('cascade');
            $table->foreign('pull_request_id', 'fk_activity_log_pr_request_id')
                ->references('id')->on('pull_requests')->onDelete('cascade');
            $table->foreign('comment_id', 'fk_activity_log_pr_comment_id')
                ->references('id')->on('comments')->onDelete('cascade');
            $table->foreign('reviewer_id', 'fk_activity_log_pr_reviewer_id')
                ->references('id')->on('pull_request_reviewers')->onDelete('cascade');
            $table->foreign('pull_request_edit_session_id', 'fk_activity_log_pr_edit_session_id')
                ->references('id')->on('pull_request_edit_sessions')->onDelete('cascade');
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
