<?php

namespace Tests\Unit\Services;

use App\Enums\DocumentCategoryStatus;
use App\Enums\FixRequestStatus;
use App\Models\DocumentCategory;
use App\Models\FixRequest;
use App\Models\PullRequest;
use App\Models\UserBranch;
use App\Services\DocumentCategoryService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentCategoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private DocumentCategoryService $categoryService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->categoryService = new DocumentCategoryService();
    }

    /**
     * @test
     * 基本ケース：mergedステータスのカテゴリのみ
     */
    public function test_getSubCategories_basic_merged_only()
    {
        $parentId = 1;
        
        // MERGED カテゴリ
        $mergedCategory = DocumentCategory::factory()->create([
            'parent_id' => $parentId,
            'status' => 'merged',
            'position' => 1,
            'user_branch_id' => null,
        ]);
        
        // DRAFT カテゴリ（user_branch_id なしなので表示されない）
        DocumentCategory::factory()->create([
            'parent_id' => $parentId,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'position' => 2,
            'user_branch_id' => 1,
        ]);

        // 実行
        $result = $this->categoryService->getSubCategories($parentId);

        // 検証
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
        $this->assertEquals($mergedCategory->id, $result->first()->id);
        $this->assertEquals('merged', $result->first()->status);
    }

    /**
     * @test
     * user_branch_id 指定時：自ブランチのDRAFTカテゴリも含む
     */
    public function test_getSubCategories_with_user_branch_draft()
    {
        $parentId = 1;
        $userBranchId = 1;
        
        // MERGED カテゴリ
        $mergedCategory = DocumentCategory::factory()->create([
            'parent_id' => $parentId,
            'status' => 'merged',
            'position' => 1,
            'user_branch_id' => null,
        ]);
        
        // 自ブランチの DRAFT カテゴリ
        $draftCategory = DocumentCategory::factory()->create([
            'parent_id' => $parentId,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'position' => 2,
            'user_branch_id' => $userBranchId,
        ]);
        
        // 他ブランチの DRAFT カテゴリ（表示されない）
        DocumentCategory::factory()->create([
            'parent_id' => $parentId,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'position' => 3,
            'user_branch_id' => 2,
        ]);

        // 実行
        $result = $this->categoryService->getSubCategories($parentId, $userBranchId);

        // 検証
        $this->assertCount(2, $result);
        $resultIds = $result->pluck('id')->toArray();
        $this->assertContains($mergedCategory->id, $resultIds);
        $this->assertContains($draftCategory->id, $resultIds);
    }

    /**
     * @test
     * edit_pull_request_id 指定時：PR紐づきのPUSHEDカテゴリも含む
     */
    public function test_getSubCategories_with_edit_pull_request_pushed()
    {
        $parentId = 1;
        $userBranchId = 1;
        $editPullRequestId = 1;
        
        // ユーザーブランチとプルリクエストを作成
        $userBranch = UserBranch::factory()->create(['id' => $userBranchId]);
        $pullRequest = PullRequest::factory()->create(['id' => $editPullRequestId]);
        
        // ユーザーブランチとプルリクエストの関連付け（多対多）
        $userBranch->pullRequests()->attach($pullRequest);
        
        // MERGED カテゴリ
        $mergedCategory = DocumentCategory::factory()->create([
            'parent_id' => $parentId,
            'status' => 'merged',
            'position' => 1,
            'user_branch_id' => null,
        ]);
        
        // PR紐づきの PUSHED カテゴリ
        $pushedCategory = DocumentCategory::factory()->create([
            'parent_id' => $parentId,
            'status' => DocumentCategoryStatus::PUSHED->value,
            'position' => 2,
            'user_branch_id' => $userBranchId,
        ]);
        
        // PR紐づきでない PUSHED カテゴリ（表示されない）
        $otherUserBranch = UserBranch::factory()->create();
        DocumentCategory::factory()->create([
            'parent_id' => $parentId,
            'status' => DocumentCategoryStatus::PUSHED->value,
            'position' => 3,
            'user_branch_id' => $otherUserBranch->id,
        ]);

        // 実行
        $result = $this->categoryService->getSubCategories($parentId, $userBranchId, $editPullRequestId);

        // 検証
        $this->assertCount(2, $result);
        $resultIds = $result->pluck('id')->toArray();
        $this->assertContains($mergedCategory->id, $resultIds);
        $this->assertContains($pushedCategory->id, $resultIds);
    }

    /**
     * @test
     * user_branch_id 指定時：適用済みFixRequestのカテゴリも含む
     */
    public function test_getSubCategories_with_applied_fix_request()
    {
        $parentId = 1;
        $userBranchId = 1;
        
        // MERGED カテゴリ
        $mergedCategory = DocumentCategory::factory()->create([
            'parent_id' => $parentId,
            'status' => 'merged',
            'position' => 1,
            'user_branch_id' => null,
        ]);
        
        // FixRequest適用済みカテゴリ
        $fixRequestCategory = DocumentCategory::factory()->create([
            'parent_id' => $parentId,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'position' => 2,
            'user_branch_id' => $userBranchId,
        ]);
        
        // 適用済み FixRequest
        FixRequest::factory()->create([
            'status' => FixRequestStatus::APPLIED->value,
            'document_category_id' => $fixRequestCategory->id,
        ]);
        
        // 適用されていない FixRequest のカテゴリ（表示されない）
        $unappliedCategory = DocumentCategory::factory()->create([
            'parent_id' => $parentId,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'position' => 3,
            'user_branch_id' => $userBranchId,
        ]);
        
        FixRequest::factory()->create([
            'status' => FixRequestStatus::PENDING->value,
            'document_category_id' => $unappliedCategory->id,
        ]);

        // 実行
        $result = $this->categoryService->getSubCategories($parentId, $userBranchId);

        // 検証
        $this->assertCount(2, $result); // merged + 適用済みFixRequest
        $resultIds = $result->pluck('id')->toArray();
        $this->assertContains($mergedCategory->id, $resultIds);
        $this->assertContains($fixRequestCategory->id, $resultIds);
        $this->assertNotContains($unappliedCategory->id, $resultIds);
    }

    /**
     * @test
     * position によるソート確認
     */
    public function test_getSubCategories_sorted_by_position()
    {
        $parentId = 1;
        
        // position の順序を意図的にバラバラに作成
        $category3 = DocumentCategory::factory()->create([
            'parent_id' => $parentId,
            'status' => 'merged',
            'position' => 3,
            'sidebar_label' => 'Third',
        ]);
        
        $category1 = DocumentCategory::factory()->create([
            'parent_id' => $parentId,
            'status' => 'merged',
            'position' => 1,
            'sidebar_label' => 'First',
        ]);
        
        $category2 = DocumentCategory::factory()->create([
            'parent_id' => $parentId,
            'status' => 'merged',
            'position' => 2,
            'sidebar_label' => 'Second',
        ]);

        // 実行
        $result = $this->categoryService->getSubCategories($parentId);

        // 検証
        $this->assertCount(3, $result);
        $positions = $result->pluck('position')->toArray();
        $this->assertEquals([1, 2, 3], $positions);
        
        $labels = $result->pluck('sidebar_label')->toArray();
        $this->assertEquals(['First', 'Second', 'Third'], $labels);
    }

    /**
     * @test
     * 複合ケース：すべての条件が組み合わさった場合
     */
    public function test_getSubCategories_complex_scenario()
    {
        $parentId = 1;
        $userBranchId = 1;
        $editPullRequestId = 1;
        
        // ユーザーブランチとプルリクエストを作成
        $userBranch = UserBranch::factory()->create(['id' => $userBranchId]);
        $pullRequest = PullRequest::factory()->create(['id' => $editPullRequestId]);
        $userBranch->pullRequests()->attach($pullRequest);
        
        // 1. MERGED カテゴリ
        $mergedCategory = DocumentCategory::factory()->create([
            'parent_id' => $parentId,
            'status' => 'merged',
            'position' => 1,
        ]);
        
        // 2. 自ブランチの DRAFT カテゴリ
        $draftCategory = DocumentCategory::factory()->create([
            'parent_id' => $parentId,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'position' => 2,
            'user_branch_id' => $userBranchId,
        ]);
        
        // 3. PR紐づきの PUSHED カテゴリ
        $pushedCategory = DocumentCategory::factory()->create([
            'parent_id' => $parentId,
            'status' => DocumentCategoryStatus::PUSHED->value,
            'position' => 3,
            'user_branch_id' => $userBranchId,
        ]);
        
        // 4. FixRequest適用済みカテゴリ
        $fixRequestCategory = DocumentCategory::factory()->create([
            'parent_id' => $parentId,
            'status' => DocumentCategoryStatus::DRAFT->value,
            'position' => 4,
            'user_branch_id' => $userBranchId,
        ]);
        
        FixRequest::factory()->create([
            'status' => FixRequestStatus::APPLIED->value,
            'document_category_id' => $fixRequestCategory->id,
        ]);

        // 実行
        $result = $this->categoryService->getSubCategories($parentId, $userBranchId, $editPullRequestId);

        // 検証
        $this->assertCount(4, $result);
        $resultIds = $result->pluck('id')->toArray();
        $this->assertContains($mergedCategory->id, $resultIds);
        $this->assertContains($draftCategory->id, $resultIds);
        $this->assertContains($pushedCategory->id, $resultIds);
        $this->assertContains($fixRequestCategory->id, $resultIds);
        
        // position でソートされていることを確認
        $positions = $result->pluck('position')->toArray();
        $this->assertEquals([1, 2, 3, 4], $positions);
    }

    /**
     * @test
     * 異なる parent_id のカテゴリは除外される
     */
    public function test_getSubCategories_filters_by_parent_id()
    {
        $parentId = 1;
        $otherParentId = 2;
        
        // 対象の parent_id のカテゴリ
        $targetCategory = DocumentCategory::factory()->create([
            'parent_id' => $parentId,
            'status' => 'merged',
            'position' => 1,
        ]);
        
        // 異なる parent_id のカテゴリ（除外される）
        DocumentCategory::factory()->create([
            'parent_id' => $otherParentId,
            'status' => 'merged',
            'position' => 1,
        ]);

        // 実行
        $result = $this->categoryService->getSubCategories($parentId);

        // 検証
        $this->assertCount(1, $result);
        $this->assertEquals($targetCategory->id, $result->first()->id);
    }

    /**
     * @test
     * FixRequest で document_category_id が null の場合は除外される
     */
    public function test_getSubCategories_excludes_fix_request_with_null_category_id()
    {
        $parentId = 1;
        $userBranchId = 1;
        
        // MERGED カテゴリ
        $mergedCategory = DocumentCategory::factory()->create([
            'parent_id' => $parentId,
            'status' => 'merged',
            'position' => 1,
        ]);
        
        // document_category_id が null の FixRequest（影響しない）
        FixRequest::factory()->create([
            'status' => FixRequestStatus::APPLIED->value,
            'document_category_id' => null,
        ]);

        // 実行
        $result = $this->categoryService->getSubCategories($parentId, $userBranchId);

        // 検証
        $this->assertCount(1, $result); // merged のみ
        $this->assertEquals($mergedCategory->id, $result->first()->id);
    }
}
