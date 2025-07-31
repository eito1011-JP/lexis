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
        Schema::create('pull_request_edit_session_diffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pull_request_edit_session_id')->constrained('pull_request_edit_sessions')->onDelete('cascade');
            $table->string('target_type');
            $table->check("target_type IN ('documents', 'categories')");
            $table->bigInteger('original_version_id')->nullable(); 
            $table->bigInteger('current_version_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pull_request_edit_session_diffs');
    }
}; 