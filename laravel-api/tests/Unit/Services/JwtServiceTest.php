<?php

namespace Tests\Unit\Services;

use App\Constants\AppConst;
use App\Exceptions\DBException;
use App\Models\RefreshToken;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
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
    public function issue_jwt_generates_and_persists_refresh_token_and_returns_payload(): void
    {
        // Arrange
        Config::set('jwt.ttl', 60);
        Config::set('jwt.refresh_ttl', 120);
        $user = User::factory()->create();

        // Act
        $result = $this->service->issueJwt($user);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('token_type', $result);
        $this->assertArrayHasKey('expires_at', $result);
        $this->assertArrayHasKey('refresh_token', $result);

        $this->assertSame('bearer', $result['token_type']);
        $this->assertSame(60 * 60, $result['expires_at']);
        $this->assertIsString($result['refresh_token']);
        $this->assertSame(AppConst::JWT_REFRESH_TOKEN_LENGTH, strlen($result['refresh_token']));

        // DBにハッシュ化されたトークンが保存されていること
        $this->assertDatabaseCount('refresh_tokens', 1);
        $record = RefreshToken::first();
        $this->assertSame($user->id, $record->user_id);
        $this->assertNotEmpty($record->hashed_refresh_token);
        $this->assertNotNull($record->expired_at);
        $this->assertSame(0, (int) $record->is_blacklisted);
        $this->assertNull($record->blacklisted_at);
    }

    #[Test]
    public function generate_refresh_token_returns_plain_token_and_stores_hashed_in_db(): void
    {
        // Arrange
        Config::set('jwt.refresh_ttl', 30);
        $user = User::factory()->create();

        // Act
        $plain = $this->service->generateRefreshToken($user->id);

        // Assert
        $this->assertSame(AppConst::JWT_REFRESH_TOKEN_LENGTH, strlen($plain));
        $this->assertDatabaseCount('refresh_tokens', 1);
        $stored = RefreshToken::first();
        $this->assertNotSame($plain, $stored->hashed_refresh_token);
        $this->assertTrue(hash('sha256', $plain) === $stored->hashed_refresh_token);
    }

    #[Test]
    public function generate_refresh_token_throws_db_exception_when_persist_fails(): void
    {
        // Arrange
        User::factory()->create();

        // 存在しないユーザーIDを使用してデータベース制約違反を発生させる
        $nonExistentUserId = 999999;

        // Act & Assert
        $this->expectException(DBException::class);
        $this->service->generateRefreshToken($nonExistentUserId);
    }
}


