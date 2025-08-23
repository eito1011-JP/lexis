<?php

namespace Tests\Unit\Services;

use App\Constants\DocumentCategoryConstants;
use App\Models\DocumentCategory;
use App\Services\DocumentCategoryService;
use Database\Seeders\DocumentCategorySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvalidArgumentException;
use Tests\TestCase;

class GetIdFromPathTest extends TestCase
{
    use DatabaseTransactions;

    private DocumentCategoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DocumentCategorySeeder::class); // DEFAULT(=1) を用意
        $this->service = new DocumentCategoryService;
    }

    public function test_returns_default_when_empty_string(): void
    {
        $id = $this->service->getIdFromPath('');
        $this->assertSame(DocumentCategoryConstants::DEFAULT_CATEGORY_ID, $id);
    }

    public function test_returns_default_when_only_slashes(): void
    {
        // 不適切な値はエラーを返す
        $this->expectException(InvalidArgumentException::class);
        $this->service->getIdFromPath('///');
    }

    public function test_single_segment_returns_leaf_id(): void
    {
        $parent = DocumentCategory::factory()->create([
            'slug' => 'parent',
            'parent_id' => DocumentCategoryConstants::DEFAULT_CATEGORY_ID,
            'status' => 'merged',
        ]);

        $id = $this->service->getIdFromPath('parent');

        // 末尾（parent）の id が返ってくる
        $this->assertSame($parent->id, $id);
    }

    public function test_multi_segments_returns_leaf_id(): void
    {
        $p = DocumentCategory::factory()->create([
            'slug' => 'parent',
            'parent_id' => DocumentCategoryConstants::DEFAULT_CATEGORY_ID,
            'status' => 'merged',
        ]);
        $c = DocumentCategory::factory()->create([
            'slug' => 'child',
            'parent_id' => $p->id,
            'status' => 'merged',
        ]);
        $g = DocumentCategory::factory()->create([
            'slug' => 'grandchild',
            'parent_id' => $c->id,
            'status' => 'merged',
        ]);

        $id = $this->service->getIdFromPath('parent/child/grandchild');

        // 末尾（grandchild）の id が返ってくる
        $this->assertSame($g->id, $id);
    }

    public function test_returns_default_when_any_segment_not_found(): void
    {
        DocumentCategory::factory()->create([
            'slug' => 'parent',
            'parent_id' => DocumentCategoryConstants::DEFAULT_CATEGORY_ID,
            'status' => 'merged',
        ]);

        // 存在しないカテゴリを入力されたらエラーを返す
        $this->expectException(InvalidArgumentException::class);
        $this->service->getIdFromPath('parent/child'); // child が存在しない
    }

    public function test_ignores_leading_trailing_and_duplicate_slashes(): void
    {
        $p = DocumentCategory::factory()->create([
            'slug' => 'parent',
            'parent_id' => DocumentCategoryConstants::DEFAULT_CATEGORY_ID,
            'status' => 'merged',
        ]);
        $c = DocumentCategory::factory()->create([
            'slug' => 'child',
            'parent_id' => $p->id,
            'status' => 'merged',
        ]);

        // 不適なリクエストを入力されたらエラーを返す
        $this->expectException(InvalidArgumentException::class);
        $this->service->getIdFromPath('/parent//child/');
    }
}
