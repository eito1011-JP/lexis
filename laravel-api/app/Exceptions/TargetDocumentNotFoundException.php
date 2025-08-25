<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * 対象ドキュメントが見つからない場合の例外
 */
class TargetDocumentNotFoundException extends Exception
{
    /**
     * コンストラクタ
     *
     * @param  string  $message  エラーメッセージ
     * @param  int  $code  エラーコード
     * @param  Exception|null  $previous  前の例外
     */
    public function __construct(
        string $message = '対象のドキュメントが見つかりません',
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * 例外をHTTPレスポンスに変換
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function render($request): JsonResponse
    {
        return response()->json([
            'result' => 'target_document_not_found',
            'message' => $this->getMessage(),
        ], 409);
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
