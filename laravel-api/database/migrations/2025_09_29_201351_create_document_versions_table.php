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
        Schema::create('document_versions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('organization_id')->index('document_versions_organization_id_foreign');
            $table->unsignedBigInteger('entity_id')->nullable()->index('fk_dv_entity');
            $table->unsignedBigInteger('user_id')->nullable()->index('document_versions_user_id_foreign');
            $table->unsignedBigInteger('user_branch_id')->nullable()->index('document_versions_user_branch_id_foreign');
            $table->unsignedBigInteger('pull_request_edit_session_id')->nullable()->index('document_versions_pull_request_edit_session_id_foreign');
            $table->string('status')->default('draft');
            $table->text('description');
            $table->string('title');
            $table->boolean('is_deleted')->default(false);
            $table->softDeletes();
            $table->timestamps();
            $table->unsignedBigInteger('category_entity_id')->nullable()->index('fk_dv_category_entity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_versions');
    }
};
