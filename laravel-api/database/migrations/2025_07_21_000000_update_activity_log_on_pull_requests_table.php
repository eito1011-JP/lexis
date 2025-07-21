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
            // fix_request_tokenカラムを追加
            $table->string('fix_request_token', 255)->nullable()->after('action');
            // fix_request_idカラムを削除
            if (Schema::hasColumn('activity_log_on_pull_requests', 'fix_request_id')) {
                $table->dropForeign(['fix_request_id']);
                $table->dropColumn('fix_request_id');
            }
            $table->unique('fix_request_token');
        });
        Schema::table('fix_requests', function (Blueprint $table) {
            $table->unique('token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_log_on_pull_requests', function (Blueprint $table) {
            // 外部キー制約を削除
            $table->dropForeign(['fix_request_token']);
            // fix_request_tokenカラムを削除
            $table->dropColumn('fix_request_token');
            // fix_request_idカラムを復元
            $table->unsignedBigInteger('fix_request_id')->nullable()->after('action');
            $table->foreign('fix_request_id')
                ->references('id')
                ->on('fix_requests')
                ->onDelete('set null');
        });
    }
}; 