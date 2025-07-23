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
        Schema::table('fix_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('base_document_version_id')->nullable()->after('document_category_id');
            $table->unsignedBigInteger('base_category_version_id')->nullable()->after('base_document_version_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fix_requests', function (Blueprint $table) {
            $table->dropColumn('base_document_version_id');
            $table->dropColumn('base_category_version_id');
        });
    }
};
