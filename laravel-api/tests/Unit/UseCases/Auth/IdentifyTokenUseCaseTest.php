<?php

namespace Tests\Unit\UseCases\Auth;

use App\Consts\ErrorType;
use App\Exceptions\AuthenticationException;
use App\Models\PreUser;
use App\Repositories\Interfaces\PreUserRepositoryInterface;
use App\UseCases\Auth\IdentifyTokenUseCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IdentifyTokenUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    /** @var \Mockery\MockInterface&PreUserRepositoryInterface */
    private PreUserRepositoryInterface $preUserRepository;

    private IdentifyTokenUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preUserRepository = Mockery::mock(PreUserRepositoryInterface::class);
        $this->useCase = new IdentifyTokenUseCase($this->preUserRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function execute_returns_true_when_token_is_valid(): void
    {
        // Arrange
        $token = 'valid-token-123';
        $this->preUserRepository
            ->shouldReceive('findActiveByToken')
            ->once()
            ->with($token)
            ->andReturn(new PreUser);

        // Act
        $result = $this->useCase->execute($token);

        // Assert
        $this->assertTrue($result['valid']);
    }

    #[Test]
    public function execute_throws_authentication_exception_when_token_is_invalid(): void
    {
        // Arrange
        $token = 'invalid-token-xyz';
        $this->preUserRepository
            ->shouldReceive('findActiveByToken')
            ->once()
            ->with($token)
            ->andReturn(null);

        // Act & Assert
        try {
            $this->useCase->execute($token);
            $this->fail('AuthenticationException was not thrown');
        } catch (AuthenticationException $e) {
            $this->assertSame(ErrorType::CODE_INVALID_TOKEN, $e->getCode());
            $this->assertSame(ErrorType::STATUS_INVALID_TOKEN, $e->getStatusCode());
        }
    }
}
