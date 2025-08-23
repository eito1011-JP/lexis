<?php

namespace Tests\Unit\Services;

use App\Models\DocumentCategory;
use App\Services\DocumentCategoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class GetIdFromPathTest extends TestCase
{
    use DatabaseTransactions;

    private DocumentCategoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DocumentCategoryService();
    }

    public function test_returns_default_when_empty_string(): void
    {
        $id = $this->service->getIdFromPath('');
        $this->assertSame(1, $id);
    }

    public function test_returns_default_when_only_slashes(): void
    {
        $id = $this->service->getIdFromPath('///');
        $this->assertSame(1, $id);
    }

    public function test_single_segment_under_default(): void
    {
        $parent = DocumentCategory::factory()->create([
            'slug' => 'parent',
            'parent_id' => 1,
            'status' => 'merged',
        ]);

        $id = $this->service->getIdFromPath('parent');
        $this->assertSame($parent->id, $id);
    }

    public function test_multi_segments(): void
    {
        $parent = DocumentCategory::factory()->create([
            'slug' => 'parent',
            'parent_id' => 1,
            'status' => 'merged',
        ]);

        $child = DocumentCategory::factory()->create([
            'slug' => 'child',
            'parent_id' => $parent->id,
            'status' => 'merged',
        ]);

        $grandchild = DocumentCategory::factory()->create([
            'slug' => 'grandchild',
            'parent_id' => $child->id,
            'status' => 'merged',
        ]);

        $id = $this->service->getIdFromPath('parent/child/grandchild');
        $this->assertSame($grandchild->id, $id);
    }

    public function test_returns_default_when_any_segment_not_found(): void
    {
        DocumentCategory::factory()->create([
            'slug' => 'parent',
            'parent_id' => 1,
            'status' => 'merged',
        ]);

        $id = $this->service->getIdFromPath('parent/child');
        $this->assertSame(1, $id);
    }
}


