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
        Schema::table('commit_document_diffs', function (Blueprint $table) {
            $table->foreign(['commit_id'])->references(['id'])->on('commits')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['first_original_version_id'])->references(['id'])->on('document_versions')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['last_current_version_id'])->references(['id'])->on('document_versions')->onUpdate('no action')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commit_document_diffs', function (Blueprint $table) {
            $table->dropForeign('commit_document_diffs_commit_id_foreign');
            $table->dropForeign('commit_document_diffs_first_original_version_id_foreign');
            $table->dropForeign('commit_document_diffs_last_current_version_id_foreign');
        });
    }
};
