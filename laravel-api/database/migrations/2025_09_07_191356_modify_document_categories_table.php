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
        Schema::table('document_categories', function (Blueprint $table) {
            // slugカラムを削除
            $table->dropColumn('slug');
            
            // sidebar_labelをtitleに変更
            $table->renameColumn('sidebar_label', 'title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_categories', function (Blueprint $table) {
            // titleをsidebar_labelに戻す
            $table->renameColumn('title', 'sidebar_label');
            
            // slugカラムを追加
            $table->string('slug')->after('id');
        });
    }
};
