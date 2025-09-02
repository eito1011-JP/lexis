<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Consts\ErrorType;
use Illuminate\Http\JsonResponse;

/**
 * ドキュメントが見つからない場合の例外
 */
class DocumentNotFoundException extends BaseException
{
    public function toResponse($request): JsonResponse
    {
        $this->setErrorCode(ErrorType::CODE_DOCUMENT_NOT_FOUND);
        $this->setErrorMessage(__('errors.MSG_DOCUMENT_NOT_FOUND'));
        $this->setStatusCode(ErrorType::STATUS_DOCUMENT_NOT_FOUND);

        return parent::toResponse($request);
    }

    /**
     * 例外をログに記録する際の処理
     */
    public function report(): bool
    {
        // この例外は通常のビジネスロジックエラーのため、ログに記録しない
        return false;
    }
}
