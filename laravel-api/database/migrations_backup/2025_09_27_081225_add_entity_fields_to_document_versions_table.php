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
            $table->unsignedBigInteger('entity_id')->nullable()->after('organization_id');
            $table->unsignedBigInteger('category_entity_id')->nullable()->after('category_id');

            $table->foreign('entity_id', 'fk_dv_entity')
                ->references('id')->on('document_version_entities')
                ->onDelete('restrict');
            $table->foreign('category_entity_id', 'fk_dv_category_entity')
                ->references('id')->on('document_category_entities')
                ->onDelete('restrict');
            
            // Remove category_id column (drop foreign key first)
            $table->dropForeign('document_versions_category_id_foreign');
            $table->dropColumn('category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_versions', function (Blueprint $table) {
            $table->dropForeign('fk_dv_entity');
            $table->dropForeign('fk_dv_category_entity');
            $table->dropColumn(['entity_id', 'category_entity_id']);
            
            // Restore category_id column
            $table->unsignedBigInteger('category_id')->nullable();
            $table->foreign('category_id')->references('id')->on('document_categories')->onDelete('restrict');
        });
    }
};
