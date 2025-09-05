<?php

namespace Tests\Unit\UseCases\Auth;

use App\UseCases\Auth\LogoutUseCase;
use Illuminate\Support\Facades\Cookie;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LogoutUseCaseTest extends TestCase
{
    private LogoutUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useCase = new LogoutUseCase();
    }

    #[Test]
    public function execute_returns_cookie(): void
    {
        // Act
        $result = $this->useCase->execute();

        // Assert
        $this->assertArrayHasKey('cookie', $result);
        $this->assertNotNull($result['cookie']);
    }

    #[Test]
    public function execute_returns_cookie_that_forgets_sid(): void
    {
        // Act
        $result = $this->useCase->execute();

        // Assert
        $cookie = $result['cookie'];
        $this->assertSame('sid', $cookie->getName());
        $this->assertNull($cookie->getValue());
        // Cookie::forget()で作成されたクッキーは過去の日付に設定されるため、有効期限が切れていることを確認
        $this->assertLessThan(time(), $cookie->getExpiresTime());
    }

    #[Test]
    public function execute_returns_expected_array_structure(): void
    {
        // Act
        $result = $this->useCase->execute();

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('cookie', $result);
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\Cookie::class, $result['cookie']);
    }
}
