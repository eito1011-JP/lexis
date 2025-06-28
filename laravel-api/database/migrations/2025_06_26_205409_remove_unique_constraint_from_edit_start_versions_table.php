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
        // テーブルを削除
        Schema::dropIfExists('edit_start_versions');

        // テーブルを再作成（UNIQUE制約なし）
        Schema::create('edit_start_versions', function (Blueprint $table) {
            $table->id();
            $table->integer('user_branch_id');
            $table->string('target_type');
            $table->integer('original_version_id')->nullable();
            $table->integer('current_version_id');
            $table->boolean('is_deleted')->default(false);
            $table->datetime('deleted_at')->nullable();
            $table->timestamps();

            $table->foreign('user_branch_id')->references('id')->on('user_branches')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 既存のデータをバックアップ
        $data = DB::table('edit_start_versions')->get();

        // テーブルを削除
        Schema::dropIfExists('edit_start_versions');

        // テーブルを再作成（UNIQUE制約あり）
        Schema::create('edit_start_versions', function (Blueprint $table) {
            $table->id();
            $table->integer('user_branch_id');
            $table->string('target_type');
            $table->integer('original_version_id')->nullable();
            $table->integer('current_version_id');
            $table->boolean('is_deleted')->default(false);
            $table->datetime('deleted_at')->nullable();
            $table->timestamps();

            $table->foreign('user_branch_id')->references('id')->on('user_branches')->onDelete('cascade');
            $table->unique(['user_branch_id', 'original_version_id']);
        });

        // データを復元
        foreach ($data as $row) {
            DB::table('edit_start_versions')->insert((array) $row);
        }
    }
};
