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
            // fix_request_tokenカラムのUNIQUE制約を削除
            $table->dropUnique(['fix_request_token']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_log_on_pull_requests', function (Blueprint $table) {
            // fix_request_tokenカラムにUNIQUE制約を再追加
            $table->unique('fix_request_token');
        });
    }
};
