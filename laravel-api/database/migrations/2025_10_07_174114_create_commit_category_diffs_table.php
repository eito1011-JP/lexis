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
        Schema::create('commit_category_diffs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('commit_id')->index('commit_category_diffs_commit_id_foreign');
            $table->unsignedBigInteger('category_entity_id');
            $table->enum('change_type', ['created', 'updated', 'deleted']);
            $table->boolean('is_title_changed')->default(false);
            $table->boolean('is_description_changed')->default(false);
            $table->unsignedBigInteger('first_original_version_id')->nullable()->index('commit_category_diffs_first_original_version_id_foreign')->comment('commit作成時に起点となるcategory_versionのid');
            $table->unsignedBigInteger('last_current_version_id')->nullable()->index('commit_category_diffs_last_current_version_id_foreign')->comment('commit確定時に終点となるcategory_versionのid');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commit_category_diffs');
    }
};
