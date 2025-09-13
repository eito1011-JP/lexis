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
        Schema::table('user_branches', function (Blueprint $table) {
            // 外部キー制約を一時的に削除
            $table->dropForeign(['user_id']);
            // unique制約を削除
            $table->dropUnique(['user_id']);
            // 外部キー制約を再作成（uniqueなし）
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_branches', function (Blueprint $table) {
            // 外部キー制約を削除
            $table->dropForeign(['user_id']);
            // unique制約を追加
            $table->unique('user_id');
            // 外部キー制約を再作成（uniqueあり）
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }
};
