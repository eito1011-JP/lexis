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
        Schema::table('document_versions', function (Blueprint $table) {
            // カラム名を変更
            $table->renameColumn('sidebar_label', 'title');
            $table->renameColumn('content', 'description');
            
            // 不要なカラムを削除
            $table->dropColumn([
                'slug',
                'file_order',
                'original_blob_sha',
                'file_path',
                'is_public'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_versions', function (Blueprint $table) {
            // カラム名を元に戻す
            $table->renameColumn('title', 'sidebar_label');
            $table->renameColumn('description', 'content');
            
            // 削除したカラムを復元
            $table->string('slug')->after('description');
            $table->integer('file_order')->after('title');
            $table->string('original_blob_sha')->nullable()->after('description');
            $table->string('file_path')->after('pull_request_edit_session_id');
            $table->boolean('is_public')->default(1)->after('is_deleted');
        });
    }
};