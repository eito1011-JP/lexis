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
            $table->enum('action_status', ['pending', 'fix_requested', 'approved'])
                ->default('pending')
                ->after('user_id')
                ->comment('レビュアーのアクション状況');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pull_request_reviewers', function (Blueprint $table) {
            $table->dropColumn('action_status');
        });
    }
};
