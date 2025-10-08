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
        Schema::table('edit_start_versions', function (Blueprint $table) {
            // 既存の外部キー制約を削除
            $table->dropForeign('edit_start_versions_user_branch_id_foreign');
            
            // 新しい外部キー制約を追加（cascade削除）
            $table->foreign(['user_branch_id'])->references(['id'])->on('user_branches')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('edit_start_versions', function (Blueprint $table) {
            // 新しい外部キー制約を削除
            $table->dropForeign('edit_start_versions_user_branch_id_foreign');
            
            // 元の外部キー制約を復元（set null）
            $table->foreign(['user_branch_id'])->references(['id'])->on('user_branches')->onUpdate('no action')->onDelete('set null');
        });
    }
};
