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
            // Add entity_id and parent_entity_id columns if they don't exist
            if (! Schema::hasColumn('document_categories', 'entity_id')) {
                $table->unsignedBigInteger('entity_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('document_categories', 'parent_entity_id')) {
                $table->unsignedBigInteger('parent_entity_id')->nullable()->after('entity_id');
            }
        });

        // Add foreign key constraints in a separate transaction
        Schema::table('document_categories', function (Blueprint $table) {
            // Check if foreign keys don't already exist before adding them
            try {
                $table->foreign('entity_id', 'fk_dc_entity')
                    ->references('id')->on('document_category_entities')
                    ->onDelete('restrict');
            } catch (\Exception $e) {
                // Foreign key already exists
            }

            try {
                $table->foreign('parent_entity_id', 'fk_dc_parent_entity')
                    ->references('id')->on('document_category_entities')
                    ->onDelete('restrict');
            } catch (\Exception $e) {
                // Foreign key already exists
            }

            // Remove parent_id column (drop foreign key first)
            if (Schema::hasColumn('document_categories', 'parent_id')) {
                $table->dropForeign('document_categories_parent_id_foreign');
                $table->dropColumn('parent_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_categories', function (Blueprint $table) {
            $table->dropForeign('fk_dc_entity');
            $table->dropForeign('fk_dc_parent_entity');
            $table->dropColumn(['entity_id', 'parent_entity_id']);

            // Restore parent_id column
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreign('parent_id')->references('id')->on('document_categories')->onDelete('restrict');
        });
    }
};
