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
        Schema::create('document_categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('entity_id')->nullable()->index('fk_dc_entity');
            $table->unsignedBigInteger('parent_entity_id')->nullable()->index('fk_dc_parent_entity');
            $table->unsignedBigInteger('organization_id')->index('document_categories_organization_id_foreign');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('user_branch_id')->nullable()->index('document_categories_user_branch_id_foreign');
            $table->unsignedBigInteger('pull_request_edit_session_id')->nullable()->index('document_categories_pull_request_edit_session_id_foreign');
            $table->boolean('is_deleted')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_categories');
    }
};
