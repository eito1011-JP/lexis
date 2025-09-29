<?php

namespace Tests\Unit\Services;

use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Models\DocumentVersion;
use App\Models\DocumentVersionEntity;
use App\Models\EditStartVersion;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\DocumentCategoryService;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DocumentServiceTest extends TestCase
{
    use DatabaseTransactions;

    private DocumentService $service;

    private User $user;

    private Organization $organization;

    private OrganizationMember $organizationMember;

    private UserBranch $activeUserBranch;

    private DocumentVersionEntity $documentEntity;

    protected function setUp(): void
    {
        parent::setUp();

        $documentCategoryService = $this->createMock(DocumentCategoryService::class);
        $this->service = new DocumentService($documentCategoryService);

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create();

        $this->organizationMember = OrganizationMember::create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'joined_at' => now(),
        ]);

        $this->activeUserBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);

        $this->documentEntity = DocumentVersionEntity::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    /** @test */
    public function アクティブなユーザーブランチがない場合はMERGEDステータスのドキュメントを取得する(): void
    {
        // Arrange
        // アクティブなユーザーブランチを削除
        $this->activeUserBranch->delete();

        $previousUserBranch = UserBranch::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
            'is_active' => false,
        ]);

        $mergedDocument = DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $previousUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $mergedDocument->id,
        ]);

        DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $previousUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $mergedDocument->id,
        ]);

        // Act
        $result = $this->service->getDocumentByWorkContext(
            $this->documentEntity->id,
            $this->user
        );

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($mergedDocument->id, $result->id);
        $this->assertEquals(DocumentStatus::MERGED->value, $result->status);
    }

    /** @test */
    public function EditStartVersionに登録されている場合は現在のバージョンを取得する(): void
    {
        // Arrange
        $mergedDocument = DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $mergedDocument->id,
        ]);

        $currentDocument = DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $this->activeUserBranch->id,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $currentDocument->id,
        ]);

        // Act
        $result = $this->service->getDocumentByWorkContext(
            $this->documentEntity->id,
            $this->user
        );

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($currentDocument->id, $result->id);
        $this->assertEquals(DocumentStatus::DRAFT->value, $result->status);
    }

    /** @test */
    public function 再編集の場合はPUSHEDとDRAFTとMERGEDステータスのドキュメントを取得する(): void
    {
        // Arrange
        $pullRequestEditSessionToken = 'test-token';

        $mergedDocument = DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'created_at' => now()->subDays(3),
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $mergedDocument->id,
        ]);

        $pushedDocument = DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::PUSHED->value,
            'user_branch_id' => $this->activeUserBranch->id,
            'created_at' => now()->subDays(1),
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $pushedDocument->id,
        ]);

        $draftDocument = DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $this->activeUserBranch->id,
            'created_at' => now(),
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $pushedDocument->id,
            'current_version_id' => $draftDocument->id,
        ]);

        // Act
        $result = $this->service->getDocumentByWorkContext(
            $this->documentEntity->id,
            $this->user,
            $pullRequestEditSessionToken
        );

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($draftDocument->id, $result->id);
        $this->assertEquals(DocumentStatus::DRAFT->value, $result->status);
    }

    /** @test */
    public function 初回編集の場合はDRAFTとMERGEDステータスのドキュメントを取得する(): void
    {
        // Arrange
        $mergedDocument = DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'created_at' => now()->subDays(2),
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $mergedDocument->id,
        ]);

        $draftDocument = DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $this->activeUserBranch->id,
            'created_at' => now(),
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $draftDocument->id,
        ]);

        // PUSHEDステータスのドキュメントも作成（これは取得されないはず）
        DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::PUSHED->value,
            'user_branch_id' => $this->activeUserBranch->id,
            'created_at' => now()->subDays(1),
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $draftDocument->id,
        ]);

        // Act
        $result = $this->service->getDocumentByWorkContext(
            $this->documentEntity->id,
            $this->user
        );

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($draftDocument->id, $result->id);
        $this->assertEquals(DocumentStatus::DRAFT->value, $result->status);
    }

    /** @test */
    public function 初回編集でDRAFTがない場合はMERGEDステータスのドキュメントを取得する(): void
    {
        // Arrange
        $mergedDocument = DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $mergedDocument->id,
        ]);

        // Act
        $result = $this->service->getDocumentByWorkContext(
            $this->documentEntity->id,
            $this->user
        );

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($mergedDocument->id, $result->id);
        $this->assertEquals(DocumentStatus::MERGED->value, $result->status);
    }

    /** @test */
    public function 他のユーザーブランチのDRAFTは取得されない(): void
    {
        // Arrange
        $otherUserBranch = UserBranch::factory()->create([
            'user_id' => User::factory()->create()->id,
            'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);

        $mergedDocument = DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::MERGED->value,
            'created_at' => now()->subDays(2),
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $mergedDocument->id,
        ]);

        // 他のユーザーのDRAFTドキュメント
        $draftDocument = DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $this->organization->id,
            'status' => DocumentStatus::DRAFT->value,
            'user_branch_id' => $otherUserBranch->id,
            'created_at' => now(),
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $otherUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $draftDocument->id,
        ]);

        // Act
        $result = $this->service->getDocumentByWorkContext(
            $this->documentEntity->id,
            $this->user
        );

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($mergedDocument->id, $result->id);
        $this->assertEquals(DocumentStatus::MERGED->value, $result->status);
    }

    /** @test */
    public function 組織が異なる場合はドキュメントが取得されない(): void
    {
        // Arrange
        $otherOrganization = Organization::factory()->create();

        $mergedDocument = DocumentVersion::factory()->create([
            'entity_id' => $this->documentEntity->id,
            'organization_id' => $otherOrganization->id,
            'status' => DocumentStatus::MERGED->value,
        ]);

        EditStartVersion::factory()->create([
            'user_branch_id' => $this->activeUserBranch->id,
            'target_type' => EditStartVersionTargetType::DOCUMENT->value,
            'original_version_id' => $mergedDocument->id,
            'current_version_id' => $mergedDocument->id,
        ]);

        // Act
        $result = $this->service->getDocumentByWorkContext(
            $this->documentEntity->id,
            $this->user
        );

        // Assert
        $this->assertNull($result);
    }
}
