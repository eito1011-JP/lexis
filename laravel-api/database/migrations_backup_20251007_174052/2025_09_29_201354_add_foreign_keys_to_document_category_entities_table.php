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
        Schema::table('document_category_entities', function (Blueprint $table) {
            $table->foreign(['organization_id'], 'fk_dce_org')->references(['id'])->on('organizations')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_category_entities', function (Blueprint $table) {
            $table->dropForeign('fk_dce_org');
        });
    }
};
