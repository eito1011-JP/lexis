<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
                CREATE TABLE pull_request_edit_session_diffs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    pull_request_edit_session_id INTEGER NOT NULL,
                    target_type TEXT NOT NULL CHECK (target_type IN ('documents', 'categories')),
                    original_version_id INTEGER,
                    current_version_id INTEGER,
                    created_at DATETIME,
                    updated_at DATETIME,
                    FOREIGN KEY (pull_request_edit_session_id) REFERENCES pull_request_edit_sessions(id) ON DELETE CASCADE
                )
            ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pull_request_edit_session_diffs');
    }
};
