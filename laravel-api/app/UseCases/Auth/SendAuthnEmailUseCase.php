<?php

namespace App\UseCases\Auth;

use App\Constants\AppConst;
use App\Consts\ErrorType;
use App\Events\PreUserCreated;
use App\Exceptions\DuplicateExecutionException;
use App\Models\User;
use App\Repositories\Interfaces\PreUserRepositoryInterface;
use App\Traits\BaseEncoding;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

class SendAuthnEmailUseCase
{
    use BaseEncoding;
    public function __construct(
        private PreUserRepositoryInterface $preUserRepository
    ) {}

    /**
     * @return array{success: bool}
     */
    public function execute(string $email, string $password): array
    {
        // 既存ユーザの重複チェック
        $user = User::byEmail($email)->first();

        if ($user) {
            throw new DuplicateExecutionException(
                ErrorType::CODE_ACCOUNT_CANNOT_BE_REGISTERED,
                __('errors.MSG_ACCOUNT_CANNOT_BE_REGISTERED'),
                ErrorType::STATUS_ACCOUNT_CANNOT_BE_REGISTERED,
            );
        }

        // 既存のpre_userを無効化
        $this->preUserRepository->updateInvalidated($email);

        // パスワードハッシュ
        $hashedPassword = Hash::make($password);

        // トークン生成（Base62）
        $token = $this->base62(AppConst::EMAIL_AUTHN_TOKEN_LENGTH);

        // pre-user登録
        $this->preUserRepository->registerPreUser($email, $hashedPassword, $token);

        // メール送信イベント
        Event::dispatch(new PreUserCreated($email, $token));

        return ['success' => true];
    }
}


