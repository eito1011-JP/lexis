<?php

namespace Tests\Unit\UseCases\Auth;

use App\Constants\AppConst;
use App\Consts\ErrorType;
use App\Events\PreUserCreated;
use App\Exceptions\DuplicateExecutionException;
use App\Models\PreUser;
use App\Models\User;
use App\Repositories\Interfaces\PreUserRepositoryInterface;
use App\UseCases\Auth\SendAuthnEmailUseCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SendAuthnEmailUseCaseTest extends TestCase
{
    use DatabaseTransactions;

    /** @var \Mockery\MockInterface&PreUserRepositoryInterface */
    private PreUserRepositoryInterface $preUserRepository;

    private SendAuthnEmailUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preUserRepository = Mockery::mock(PreUserRepositoryInterface::class);
        $this->useCase = new SendAuthnEmailUseCase($this->preUserRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function execute_succeeds_when_user_does_not_exist_and_dispatches_event(): void
    {
        // Arrange
        $email = 'new-user@example.com';
        $password = 'strongPass123';

        // updateInvalidated は必ず呼ばれる
        $this->preUserRepository
            ->shouldReceive('updateInvalidated')
            ->once()
            ->with($email)
            ->andReturn(1);

        // registerPreUser への引数検証（ハッシュと BASE62 長さ）
        $captured = ['token' => null, 'hashed' => null];
        $this->preUserRepository
            ->shouldReceive('registerPreUser')
            ->once()
            ->with(
                Mockery::on(fn ($v) => $v === $email),
                Mockery::on(function ($hashed) use (&$captured, $password) {
                    $captured['hashed'] = $hashed;
                    return Hash::check($password, $hashed);
                }),
                Mockery::on(function ($token) use (&$captured) {
                    $captured['token'] = $token;
                    $this->assertSame(AppConst::EMAIL_AUTHN_TOKEN_LENGTH, strlen($token));
                    $this->assertMatchesRegularExpression('/^[0-9a-zA-Z]+$/', $token);
                    return true;
                })
            )
            ->andReturn(new PreUser());

        Event::fake();

        // Act
        $result = $this->useCase->execute($email, $password);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertIsString($captured['token']);
        $this->assertIsString($captured['hashed']);

        Event::assertDispatched(PreUserCreated::class, function (PreUserCreated $event) use ($email, $captured) {
            $this->assertSame($email, $event->email);
            $this->assertSame($captured['token'], $event->token);
            return true;
        });
    }

    #[Test]
    public function execute_throws_duplicate_exception_when_user_already_exists(): void
    {
        // Arrange
        $email = 'exists@example.com';
        $password = 'strongPass123';

        // 既存ユーザーを作成
        User::create([
            'email' => $email,
            'password' => bcrypt('whatever123'),
        ]);

        // updateInvalidated/registerPreUser は呼ばれないこと
        $this->preUserRepository->shouldNotReceive('updateInvalidated');
        $this->preUserRepository->shouldNotReceive('registerPreUser');

        // Act & Assert
        try {
            $this->useCase->execute($email, $password);
            $this->fail('DuplicateExecutionException was not thrown');
        } catch (DuplicateExecutionException $e) {
            $this->assertSame(ErrorType::CODE_ACCOUNT_CANNOT_BE_REGISTERED, $e->getCode());
            $this->assertSame(ErrorType::STATUS_ACCOUNT_CANNOT_BE_REGISTERED, $e->getStatusCode());
        }
    }
}


