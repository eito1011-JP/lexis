<?php

namespace Tests\Unit\Services;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\OrganizationService;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OrganizationServiceTest extends TestCase
{
    use DatabaseTransactions;

    private OrganizationService $service;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OrganizationService();
        $this->user = User::factory()->create();
        $this->organization = Organization::factory()->create();
    }

    /**
     * @test
     */
    public function validateUserBelongsToOrganization_ユーザーが組織に所属している場合は例外がスローされない()
    {
        // Arrange
        OrganizationMember::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);

        // Act & Assert
        $this->service->validateUserBelongsToOrganization($this->user->id, $this->organization->id);
        $this->assertTrue(true); // 例外がスローされなければテスト成功
    }

    /**
     * @test
     */
    public function validateUserBelongsToOrganization_ユーザーが組織に所属していない場合はNotFoundException例外がスローされる()
    {
        // Arrange - OrganizationMemberレコードを作成しない

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->service->validateUserBelongsToOrganization($this->user->id, $this->organization->id);
    }

    /**
     * @test
     */
    public function validateUserBelongsToOrganization_存在しないユーザーIDの場合はNotFoundException例外がスローされる()
    {
        // Arrange
        $nonExistentUserId = 99999;

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->service->validateUserBelongsToOrganization($nonExistentUserId, $this->organization->id);
    }

    /**
     * @test
     */
    public function validateUserBelongsToOrganization_存在しない組織IDの場合はNotFoundException例外がスローされる()
    {
        // Arrange
        $nonExistentOrganizationId = 99999;

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->service->validateUserBelongsToOrganization($this->user->id, $nonExistentOrganizationId);
    }

    /**
     * @test
     */
    public function validateUserBelongsToOrganization_ユーザーが別の組織に所属している場合はNotFoundException例外がスローされる()
    {
        // Arrange
        $anotherOrganization = Organization::factory()->create();
        OrganizationMember::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $anotherOrganization->id,
        ]);

        // Act & Assert
        $this->expectException(NotFoundException::class);
        $this->service->validateUserBelongsToOrganization($this->user->id, $this->organization->id);
    }

    /**
     * @test
     */
    public function validateUserBelongsToOrganization_複数の組織に所属しているユーザーが正しい組織でチェックされる場合は例外がスローされない()
    {
        // Arrange
        $anotherOrganization = Organization::factory()->create();
        
        // 複数の組織に所属させる
        OrganizationMember::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $this->organization->id,
        ]);
        OrganizationMember::factory()->create([
            'user_id' => $this->user->id,
            'organization_id' => $anotherOrganization->id,
        ]);

        // Act & Assert
        $this->service->validateUserBelongsToOrganization($this->user->id, $this->organization->id);
        $this->assertTrue(true); // 例外がスローされなければテスト成功
    }
}
