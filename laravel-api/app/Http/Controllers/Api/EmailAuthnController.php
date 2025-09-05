<?php

namespace App\Http\Controllers\Api;

use App\Consts\ErrorType;
use App\Exceptions\AuthenticationException;
use App\Exceptions\DuplicateExecutionException;
use App\Http\Requests\Api\Auth\SendAuthnEmailRequest;
use App\Http\Requests\Api\Auth\IdentifyTokenRequest;
use App\Http\Requests\Api\Auth\SigninWithEmailRequest;
use App\Exceptions\TooManyRequestsException;
use App\Exceptions\NoAccountException;
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
        try {
            $result = $this->signinWithEmailUseCase->execute(
                $request->email,
                $request->password,
                $request
            );

        } catch (TooManyRequestsException) {
            return $this->sendError(
                ErrorType::CODE_TOO_MANY_REQUESTS,
                __('errors.MSG_TOO_MANY_REQUESTS'),
                ErrorType::STATUS_TOO_MANY_REQUESTS,
            );
        }
        catch (NoAccountException) {
            return $this->sendError(
                ErrorType::CODE_NO_ACCOUNT,
                __('errors.MSG_NO_ACCOUNT'),
                ErrorType::STATUS_NO_ACCOUNT,
            );
        }
        catch (AuthenticationException) {
            return $this->sendError(
                ErrorType::CODE_AUTHENTICATION_FAILED,
                __('errors.MSG_AUTHENTICATION_FAILED'),
                ErrorType::STATUS_AUTHENTICATION_FAILED,
            );
        }
        catch (Exception $e) {
            // 例外の詳細をログに出す（stack と single に出力される）
            Log::error($e);
            return $this->sendError(
                ErrorType::CODE_INTERNAL_ERROR,
                __('errors.MSG_INTERNAL_ERROR'),
                ErrorType::STATUS_INTERNAL_ERROR,
                LogLevel::ERROR,
            );
        }

        return response()->json($result['user'])->withCookie($result['cookie']);
    }
}

