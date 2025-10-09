<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_branch_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('user_branch_id');
            $table->timestamps();
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade');
            $table->foreign('user_branch_id')
                ->references('id')->on('user_branches')
                ->onDelete('cascade');
            $table->unique('user_branch_id', 'unique_active_session');
        });

        // 3. user_branchesテーブルを更新
        Schema::table('user_branches', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('is_active');
        });

        // 4. user_idカラムをcreator_idにリネーム
        Schema::table('user_branches', function (Blueprint $table) {
            $table->renameColumn('user_id', 'creator_id');
        });

        // 5. 新しい外部キー制約を追加
        Schema::table('user_branches', function (Blueprint $table) {
            $table->foreign('creator_id')
                ->references('id')->on('users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. user_branchesの外部キー制約を削除
        Schema::table('user_branches', function (Blueprint $table) {
            $table->dropForeign(['creator_id']);
        });

        // 2. creator_idをuser_idにリネーム
        Schema::table('user_branches', function (Blueprint $table) {
            $table->renameColumn('creator_id', 'user_id');
        });

        // 3. is_activeカラムと外部キー制約を戻す
        Schema::table('user_branches', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
            
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('set null');
        });

        // 4. user_branch_sessionsからis_activeの状態を戻す（可能な範囲で）
        DB::statement('
            UPDATE user_branches ub
            INNER JOIN user_branch_sessions ubs ON ub.id = ubs.user_branch_id
            SET ub.is_active = 1
        ');

        // 5. user_branch_sessionsテーブルを削除
        Schema::dropIfExists('user_branch_sessions');
    }
};