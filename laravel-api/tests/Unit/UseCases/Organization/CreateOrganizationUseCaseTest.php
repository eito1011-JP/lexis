<?php

namespace Tests\Unit\UseCases\Organization;

use App\Consts\Flag;
use App\Consts\ErrorType;
use App\Enums\OrganizationRoleBindingRole;
use App\Exceptions\AuthenticationException;
use App\Exceptions\DuplicateExecutionException;
use App\Models\Organization;
use App\Models\PreUser;
use App\Models\User;
use App\Repositories\Interfaces\PreUserRepositoryInterface;
use App\UseCases\Organization\CreateOrganizationUseCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreateOrganizationUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    /** @var \Mockery\MockInterface&PreUserRepositoryInterface */
    private PreUserRepositoryInterface $preUserRepository;

    private CreateOrganizationUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preUserRepository = Mockery::mock(PreUserRepositoryInterface::class);
        $this->useCase = new CreateOrganizationUseCase($this->preUserRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function execute_creates_user_organization_and_bindings_when_token_is_valid_and_unique(): void
    {
        // Arrange
        $organizationUuid = 'org-uuid-001';
        $organizationName = 'Acme Inc.';
        $token = 'valid-token';

        // update() がDBへ反映されるよう、永続化済みの PreUser を用意
        $preUser = PreUser::create([
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
            'token' => $token,
            'is_invalidated' => false,
        ]);

        $this->preUserRepository
            ->shouldReceive('findActiveByToken')
            ->once()
            ->with($token)
            ->andReturn($preUser);

        // Act
        $result = $this->useCase->execute($organizationUuid, $organizationName, $token);

        // Assert: 戻り値
        $this->assertArrayHasKey('organization', $result);
        $this->assertArrayHasKey('user', $result);

        /** @var Organization $org */
        $org = $result['organization'];
        /** @var User $user */
        $user = $result['user'];

        $this->assertInstanceOf(Organization::class, $org);
        $this->assertInstanceOf(User::class, $user);

        // DB 検証
        $this->assertDatabaseHas('organizations', [
            'uuid' => $organizationUuid,
            'name' => $organizationName,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'user@example.com',
        ]);

        $this->assertDatabaseHas('organization_members', [
            'organization_id' => $org->id,
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('organization_role_bindings', [
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrganizationRoleBindingRole::OWNER->value,
        ]);

        // pre_users の無効化が行われたこと
        $this->assertDatabaseHas('pre_users', [
            'email' => 'user@example.com',
            'is_invalidated' => Flag::TRUE,
        ]);
    }

    #[Test]
    public function execute_throws_authentication_exception_when_token_not_found(): void
    {
        // Arrange
        $this->preUserRepository
            ->shouldReceive('findActiveByToken')
            ->once()
            ->with('bad-token')
            ->andReturn(null);

        // Act & Assert
        try {
            $this->useCase->execute('org-uuid', 'Acme', 'bad-token');
            $this->fail('AuthenticationException was not thrown');
        } catch (AuthenticationException $e) {
            $this->assertSame(ErrorType::CODE_INVALID_TOKEN, $e->getCode());
            $this->assertSame(ErrorType::STATUS_INVALID_TOKEN, $e->getStatusCode());
        }
    }

    #[Test]
    public function execute_throws_duplicate_exception_when_organization_uuid_or_name_exists(): void
    {
        // Arrange
        // 既存の組織を作成（uuidが衝突する状態にする）
        Organization::create([
            'uuid' => 'dup-uuid',
            'name' => 'Dup Org',
        ]);

        $preUser = PreUser::create([
            'email' => 'dup@example.com',
            'password' => Hash::make('password123'),
            'token' => 't',
        ]);

        $this->preUserRepository
            ->shouldReceive('findActiveByToken')
            ->once()
            ->with('valid-token')
            ->andReturn($preUser);

        // Act & Assert (uuid が既存)
        try {
            $this->useCase->execute('dup-uuid', 'Another Name', 'valid-token');
            $this->fail('DuplicateExecutionException was not thrown with duplicated uuid');
        } catch (DuplicateExecutionException $e) {
            $this->assertSame(ErrorType::CODE_DUPLICATE_EXECUTION, $e->getCode());
            $this->assertSame(ErrorType::STATUS_DUPLICATE_EXECUTION, $e->getStatusCode());
        }
    }
}


