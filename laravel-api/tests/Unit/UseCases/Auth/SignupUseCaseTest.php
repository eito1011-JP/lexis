<?php

namespace Tests\Unit\UseCases\Auth;

use App\Models\Session;
use App\Models\User;
use App\UseCases\Auth\SignupUseCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SignupUseCaseTest extends TestCase
{
    // use DatabaseTransactions;

    // private SignupUseCase $signupUseCase;

    // protected function setUp(): void
    // {
    //     parent::setUp();
    //     $this->signupUseCase = new SignupUseCase();
    // }

    // /**
    //  * 正常なユーザー登録のテスト
    //  */
    // public function testExecuteWithValidData(): void
    // {
    //     $email = 'test@example.com';
    //     $password = 'password123';

    //     $result = $this->signupUseCase->execute($email, $password);

    //     $this->assertTrue($result['success']);
    //     $this->assertArrayHasKey('user', $result);
    //     $this->assertArrayHasKey('sessionId', $result);
    //     $this->assertEquals($email, $result['user']['email']);

    //     // データベースにユーザーが作成されていることを確認
    //     $this->assertDatabaseHas('users', [
    //         'email' => $email,
    //     ]);

    //     // セッションが作成されていることを確認
    //     $this->assertDatabaseHas('sessions', [
    //         'id' => $result['sessionId'],
    //     ]);
    // }

    // /**
    //  * 重複したメールアドレスでの登録テスト
    //  */
    // public function testExecuteWithDuplicateEmail(): void
    // {
    //     // 最初のユーザーを作成
    //     User::create([
    //         'email' => 'test@example.com',
    //         'password' => bcrypt('password123'),
    //     ]);

    //     $result = $this->signupUseCase->execute('test@example.com', 'password456');

    //     $this->assertFalse($result['success']);
    //     $this->assertArrayHasKey('error', $result);
    // }

    // /**
    //  * セッション作成のテスト
    //  */
    // public function testSessionCreation(): void
    // {
    //     $email = 'test@example.com';
    //     $password = 'password123';

    //     $result = $this->signupUseCase->execute($email, $password);

    //     $session = Session::find($result['sessionId']);
    //     $this->assertNotNull($session);
    //     $this->assertEquals($result['user']['id'], $session->user_id);

    //     $sessionData = json_decode($session->sess, true);
    //     $this->assertEquals($email, $sessionData['email']);
    //     $this->assertEquals($result['user']['id'], $sessionData['userId']);
    // }
}
