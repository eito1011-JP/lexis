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
        Schema::table('category_versions', function (Blueprint $table) {
            $table->foreign(['organization_id'], 'document_categories_organization_id_foreign')->references(['id'])->on('organizations')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['user_branch_id'], 'document_categories_user_branch_id_foreign')->references(['id'])->on('user_branches')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['entity_id'], 'fk_cv_entity')->references(['id'])->on('category_entities')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['parent_entity_id'], 'fk_cv_parent_entity')->references(['id'])->on('category_entities')->onUpdate('no action')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('category_versions', function (Blueprint $table) {
            $table->dropForeign('document_categories_organization_id_foreign');
            $table->dropForeign('document_categories_user_branch_id_foreign');
            $table->dropForeign('fk_cv_entity');
            $table->dropForeign('fk_cv_parent_entity');
        });
    }
};
