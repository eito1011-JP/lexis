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
        Schema::table('pull_request_reviewers', function (Blueprint $table) {
            $table->foreign(['pull_request_id'])->references(['id'])->on('pull_requests')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['user_id'])->references(['id'])->on('users')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pull_request_reviewers', function (Blueprint $table) {
            $table->dropForeign('pull_request_reviewers_pull_request_id_foreign');
            $table->dropForeign('pull_request_reviewers_user_id_foreign');
        });
    }
};
