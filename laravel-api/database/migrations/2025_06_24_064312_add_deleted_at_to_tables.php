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
        // document_versionsテーブルにdeleted_atカラムを追加
        Schema::table('document_versions', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable();
        });

        // usersテーブルにdeleted_atカラムを追加
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // document_versionsテーブルからdeleted_atカラムを削除
        Schema::table('document_versions', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });

        // usersテーブルからdeleted_atカラムを削除
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
    }
};
