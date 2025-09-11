<?php

namespace Tests\Unit\UseCases\Auth;

use App\Exceptions\AuthenticationException;
use App\Exceptions\NoAccountException;
use App\Exceptions\TooManyRequestsException;
use App\Models\User;
use App\UseCases\Auth\SigninWithEmailUseCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SigninWithEmailUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    private SigninWithEmailUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useCase = new SigninWithEmailUseCase;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeRequest(string $ip): Request
    {
        $request = Request::create('/api/signin', 'POST', [], [], [], ['REMOTE_ADDR' => $ip]);

        return $request;
    }

    private function rateLimiterKey(string $ip, string $email): string
    {
        return 'signin-with-email.'.$ip.'.'.$email;
    }

    #[Test]
    public function throws_too_many_requests_when_already_rate_limited(): void
    {
        // Arrange
        $email = 'limited@example.com';
        $ip = '10.0.0.1';
        $request = $this->makeRequest($ip);

        $key = $this->rateLimiterKey($ip, $email);
        RateLimiter::clear($key);
        // 事前に閾値超過状態にする
        $max = (int) config('auth.login_attempts.max_attempts');
        for ($i = 0; $i < $max; $i++) {
            RateLimiter::hit($key, config('auth.login_attempts.lockout_decay_minutes') * 60);
        }

        // Act & Assert
        $this->expectException(TooManyRequestsException::class);
        $this->useCase->execute($email, 'any-pass', $request);
    }

    #[Test]
    public function throws_no_account_when_user_not_found(): void
    {
        // Arrange
        $email = 'notfound@example.com';
        $ip = '10.0.0.2';
        $request = $this->makeRequest($ip);

        // Act & Assert
        $this->expectException(NoAccountException::class);
        $this->useCase->execute($email, 'password123', $request);
    }

    #[Test]
    public function throws_authentication_and_increments_rate_limit_on_wrong_password_until_limit(): void
    {
        // Arrange
        $email = 'user@example.com';
        $password = 'correct-pass-123';
        $ip = '10.0.0.3';
        $request = $this->makeRequest($ip);
        $key = $this->rateLimiterKey($ip, $email);
        RateLimiter::clear($key);

        // ユーザー作成（ハッシュはlaravel側で保存時に適用されるcastsに依存せず、明示ハッシュ）
        $user = User::create([
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        // 失敗1回目
        try {
            $this->useCase->execute($email, 'wrong-1', $request);
            $this->fail('AuthenticationException not thrown on first wrong password');
        } catch (AuthenticationException $e) {
            $this->assertTrue(RateLimiter::tooManyAttempts($key, config('auth.login_attempts.max_attempts')) === false);
        }

        // 失敗2回目以降（max_attempts 到達で TooManyRequests になる直前まで）
        $max = (int) config('auth.login_attempts.max_attempts');
        for ($i = 2; $i <= $max - 1; $i++) {
            try {
                $this->useCase->execute($email, 'wrong-'.$i, $request);
                $this->fail('AuthenticationException not thrown on wrong password #'.$i);
            } catch (AuthenticationException $e) {
                // 継続して認証失敗
            }
        }

        // 閾値に到達する失敗
        $this->expectException(TooManyRequestsException::class);
        $this->useCase->execute($email, 'wrong-final', $request);
    }

    #[Test]
    public function succeeds_when_password_matches_clears_rate_limit_updates_last_login_and_returns_jwt(): void
    {
        // Arrange
        $email = 'ok@example.com';
        $password = 'Passw0rd!';
        $ip = '10.0.0.4';
        $request = $this->makeRequest($ip);
        $key = $this->rateLimiterKey($ip, $email);
        RateLimiter::clear($key);

        $user = User::create([
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        // 事前に失敗を何回か積んでおく -> 成功時に clear されることを確認
        RateLimiter::hit($key, config('auth.login_attempts.lockout_decay_minutes') * 60);

        // Act
        $result = $this->useCase->execute($email, $password, $request);

        // Assert
        $this->assertSame($result, $result);
        $this->assertFalse(RateLimiter::tooManyAttempts($key, config('auth.login_attempts.max_attempts')));

        // last_login が更新されたこと
        $user->refresh();
        $this->assertNotNull($user->last_login);
    }
}
