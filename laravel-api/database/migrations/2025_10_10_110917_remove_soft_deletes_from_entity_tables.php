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
        // document_entitiesテーブルからdeleted_atカラムを削除
        Schema::table('document_entities', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        // category_entitiesテーブルからdeleted_atカラムを削除
        Schema::table('category_entities', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // document_entitiesテーブルにdeleted_atカラムを復元
        Schema::table('document_entities', function (Blueprint $table) {
            $table->softDeletes();
        });

        // category_entitiesテーブルにdeleted_atカラムを復元
        Schema::table('category_entities', function (Blueprint $table) {
            $table->softDeletes();
        });
    }
};
