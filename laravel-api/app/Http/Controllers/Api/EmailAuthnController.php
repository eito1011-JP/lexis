<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Auth\SendAuthnEmailRequest;
use App\Http\Requests\Api\Auth\IdentifyTokenRequest;
use App\UseCases\Auth\SendAuthnEmailUseCase;
use App\UseCases\Auth\IdentifyTokenUseCase;
use Illuminate\Http\JsonResponse;

class EmailAuthnController extends ApiBaseController
{
    public function __construct(
        private SendAuthnEmailUseCase $sendAuthnEmailUseCase,
        private IdentifyTokenUseCase $identifyTokenUseCase,
    ) {}

    /**
     * 事前登録メール送信
     */
    public function sendAuthnEmail(SendAuthnEmailRequest $request): JsonResponse
    {
        $this->sendAuthnEmailUseCase->execute($request->email, $request->password);

        return response()->json();
    }

    /**
     * 事前登録トークンの検証
     */
    public function identifyToken(IdentifyTokenRequest $request): JsonResponse
    {
        $result = $this->identifyTokenUseCase->execute($request->token);

        return response()->json($result);
    }
}

