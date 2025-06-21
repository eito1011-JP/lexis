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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->string('sidebar_label');
            $table->string('slug')->unique();
            $table->boolean('is_public')->default(false);
            $table->string('status')->default('draft');
            $table->string('last_edited_by')->nullable();
            $table->integer('file_order')->default(0);
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('document_categories')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
}; 