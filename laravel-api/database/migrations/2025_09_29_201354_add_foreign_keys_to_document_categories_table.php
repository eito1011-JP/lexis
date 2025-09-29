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
            $table->foreign(['organization_id'])->references(['id'])->on('organizations')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['pull_request_edit_session_id'])->references(['id'])->on('pull_request_edit_sessions')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['user_branch_id'])->references(['id'])->on('user_branches')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['entity_id'], 'fk_dc_entity')->references(['id'])->on('document_category_entities')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['parent_entity_id'], 'fk_dc_parent_entity')->references(['id'])->on('document_category_entities')->onUpdate('no action')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_categories', function (Blueprint $table) {
            $table->dropForeign('document_categories_organization_id_foreign');
            $table->dropForeign('document_categories_pull_request_edit_session_id_foreign');
            $table->dropForeign('document_categories_user_branch_id_foreign');
            $table->dropForeign('fk_dc_entity');
            $table->dropForeign('fk_dc_parent_entity');
        });
    }
};
