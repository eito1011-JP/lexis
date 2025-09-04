<?php

namespace App\Http\Controllers\Api;

use App\Consts\ErrorType;
use App\Exceptions\AuthenticationException;
use App\Exceptions\DuplicateExecutionException;
use App\Http\Requests\Api\Auth\SendAuthnEmailRequest;
use App\Http\Requests\Api\Auth\IdentifyTokenRequest;
use App\Http\Requests\Api\Auth\SigninWithEmailRequest;
use App\UseCases\Auth\SendAuthnEmailUseCase;
use App\UseCases\Auth\IdentifyTokenUseCase;
use App\UseCases\Auth\SigninWithEmailUseCase;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Psr\Log\LogLevel;

class EmailAuthnController extends ApiBaseController
{
    public function __construct(
        private SendAuthnEmailUseCase $sendAuthnEmailUseCase,
        private IdentifyTokenUseCase $identifyTokenUseCase,
        private SigninWithEmailUseCase $signinWithEmailUseCase,
    ) {}

    /**
     * 事前登録メール送信
     */
    public function sendAuthnEmail(SendAuthnEmailRequest $request): JsonResponse
    {
        try {
            $this->sendAuthnEmailUseCase->execute($request->email, $request->password);
        } catch (DuplicateExecutionException $e) {
            return $this->sendError(
                ErrorType::CODE_DUPLICATE_EXECUTION,
                __('errors.MSG_DUPLICATE_EXECUTION'),
                ErrorType::STATUS_DUPLICATE_EXECUTION,
            );
        } catch (Exception $e) {
            Log::error($e);
            return $this->sendError(
                ErrorType::CODE_INTERNAL_ERROR,
                __('errors.MSG_INTERNAL_ERROR'),
                ErrorType::STATUS_INTERNAL_ERROR,
            );
        }

        return response()->json();
    }

    /**
     * 事前登録トークンの検証
     */
    public function identifyToken(IdentifyTokenRequest $request): JsonResponse
    {
        try {
            $result = $this->identifyTokenUseCase->execute($request->token);   
        } catch (AuthenticationException) {
            return $this->sendError(
                ErrorType::CODE_INVALID_TOKEN,
                __('errors.MSG_INVALID_TOKEN'),
                ErrorType::STATUS_INVALID_TOKEN,
            );
        } catch (Exception) {
            return $this->sendError(
                ErrorType::CODE_INTERNAL_ERROR,
                __('errors.MSG_INTERNAL_ERROR'),
                ErrorType::STATUS_INTERNAL_ERROR,
                LogLevel::ERROR,
            );
        }

        return response()->json($result);
    }

    /**
     * メールアドレスとパスワードでサインイン
     */
    public function signinWithEmail(SigninWithEmailRequest $request): JsonResponse
    {
        $result = $this->signinWithEmailUseCase->execute(
            $request->email,
            $request->password,
            $request
        );

        return response()->json($result);
    }
}

