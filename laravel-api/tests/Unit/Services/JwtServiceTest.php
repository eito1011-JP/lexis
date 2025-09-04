<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JwtServiceTest extends TestCase
{
    use DatabaseTransactions;

    private JwtService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new JwtService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function issue_jwt_creates_pat_and_returns_payload(): void
    {
        // Arrange
        Config::set('jwt.ttl', 60);
        Config::set('sanctum.expiration', 60);
        $user = User::factory()->create();

        // Act
        $result = $this->service->issueJwt($user);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('token_type', $result);
        $this->assertArrayHasKey('expires_at', $result);

        $this->assertSame('bearer', $result['token_type']);
        $this->assertSame(60 * 60, $result['expires_at']);
    }

    #[Test]
    public function sanctum_expiration_null_falls_back_to_jwt_ttl(): void
    {
        // Arrange
        Config::set('sanctum.expiration', null);
        Config::set('jwt.ttl', 45);
        $user = User::factory()->create();

        // Act
        $result = $this->service->issueJwt($user);

        // Assert
        $this->assertSame(45 * 60, $result['expires_at']);
    }
}


