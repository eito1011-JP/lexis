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
            $table->string('old_pull_request_title')->nullable()->after('action')->comment('変更前のプルリクエストタイトル');
            $table->string('new_pull_request_title')->nullable()->after('old_pull_request_title')->comment('変更後のプルリクエストタイトル');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_log_on_pull_requests', function (Blueprint $table) {
            $table->dropColumn(['old_pull_request_title', 'new_pull_request_title']);
        });
    }
};
