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
            $table->id();
            $table->foreignId('commit_id')->constrained('commits')->onDelete('cascade');
            $table->unsignedBigInteger('category_entity_id');
            $table->enum('change_type', ['created', 'updated', 'deleted']);
            $table->boolean('is_title_changed')->default(false);
            $table->boolean('is_description_changed')->default(false);
            $table->unsignedBigInteger('first_original_version_id')->nullable()->comment('commit作成時に起点となるcategory_versionのid');
            $table->unsignedBigInteger('last_current_version_id')->nullable()->comment('commit確定時に終点となるcategory_versionのid');
            $table->timestamps();

            $table->foreign('first_original_version_id')->references('id')->on('category_versions')->onDelete('set null');
            $table->foreign('last_current_version_id')->references('id')->on('category_versions')->onDelete('set null');
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
