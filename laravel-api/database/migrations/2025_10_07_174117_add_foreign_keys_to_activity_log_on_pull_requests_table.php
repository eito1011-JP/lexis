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
        Schema::table('activity_log_on_pull_requests', function (Blueprint $table) {
            $table->foreign(['commit_id'])->references(['id'])->on('commits')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['comment_id'], 'fk_activity_log_pr_comment_id')->references(['id'])->on('comments')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['pull_request_id'], 'fk_activity_log_pr_request_id')->references(['id'])->on('pull_requests')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['reviewer_id'], 'fk_activity_log_pr_reviewer_id')->references(['id'])->on('pull_request_reviewers')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['user_id'], 'fk_activity_log_pr_user_id')->references(['id'])->on('users')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_log_on_pull_requests', function (Blueprint $table) {
            $table->dropForeign('activity_log_on_pull_requests_commit_id_foreign');
            $table->dropForeign('fk_activity_log_pr_comment_id');
            $table->dropForeign('fk_activity_log_pr_request_id');
            $table->dropForeign('fk_activity_log_pr_reviewer_id');
            $table->dropForeign('fk_activity_log_pr_user_id');
        });
    }
};
