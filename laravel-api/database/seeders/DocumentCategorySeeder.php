<?php

namespace Database\Seeders;

use App\Constants\DocumentCategoryConstants;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocumentCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('document_categories')->updateOrInsert(
            ['id' => DocumentCategoryConstants::DEFAULT_CATEGORY_ID],
            [
                'slug' => DocumentCategoryConstants::DEFAULT_CATEGORY_SLUG,
                'sidebar_label' => DocumentCategoryConstants::DEFAULT_CATEGORY_SIDEBAR_LABEL,
                'position' => 1,
                'description' => null,
                'status' => 'merged',
                'parent_id' => null,
                'user_branch_id' => null,
                'pull_request_edit_session_id' => null,
                'is_deleted' => false,
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}


