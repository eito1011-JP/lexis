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
            $table->foreign(['organization_id'])->references(['id'])->on('organizations')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['user_branch_id'])->references(['id'])->on('user_branches')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['user_id'])->references(['id'])->on('users')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['category_entity_id'], 'fk_dv_category_entity')->references(['id'])->on('category_entities')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['entity_id'], 'fk_dv_entity')->references(['id'])->on('document_entities')->onUpdate('no action')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_versions', function (Blueprint $table) {
            $table->dropForeign('document_versions_organization_id_foreign');
            $table->dropForeign('document_versions_user_branch_id_foreign');
            $table->dropForeign('document_versions_user_id_foreign');
            $table->dropForeign('fk_dv_category_entity');
            $table->dropForeign('fk_dv_entity');
        });
    }
};
