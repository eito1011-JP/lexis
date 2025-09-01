<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Auth\SendAuthnEmailRequest;
use App\UseCases\Auth\SendAuthnEmailUseCase;
use Illuminate\Http\JsonResponse;

class EmailAuthnController extends ApiBaseController
{
    public function __construct(
        private SendAuthnEmailUseCase $sendAuthnEmailUseCase
    ) {}

    /**
     * 事前登録メール送信
     */
    public function sendAuthnEmail(SendAuthnEmailRequest $request): JsonResponse
    {
        $this->sendAuthnEmailUseCase->execute($request->email, $request->password);

        return response()->json();
    }
}

