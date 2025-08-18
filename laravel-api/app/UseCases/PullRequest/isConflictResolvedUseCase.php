<?php

namespace App\UseCases\PullRequest;

use Illuminate\Support\Facades\Log;

class IsConflictResolvedUseCase
{
    /**
     * フロントのコンフリクト修正一時検証を実行
     * - 本文(編集用)テキストにコンフリクトマーカーが含まれていないかを確認
     * - 含まれていなければ各ファイルの状態をOKとして返す
     * - 含まれていればエラーを返す
     *
     * @param array $file 検証対象のファイルオブジェクト
     * @return array{is_conflict: bool}
     * @throws \InvalidArgumentException
     */
    public function execute(array $file): array
    {
        Log::info('IsConflictResolvedUseCase: 開始', [
            'filename' => $file['filename'] ?? 'unknown'
        ]);

        $markerPatterns = [
            '/^<<<<<<<.*/m',
            '/^=======$/m',
            '/^>>>>>>>.*/m',
        ];

        $filename = $file['filename'] ?? null;
        $body = (string)($file['body'] ?? '');
        
        if (!$filename) {
            throw new \InvalidArgumentException('filename は必須です');
        }

        $hasMarker = false;
        foreach ($markerPatterns as $pattern) {
            if (preg_match($pattern, $body)) {
                $hasMarker = true;
                Log::info('HandleFixConflictTemporaryUseCase: コンフリクトマーカーを検出', [
                    'filename' => $filename,
                    'pattern' => $pattern
                ]);
                break;
            }
        }

        $result = [
            'is_conflict' => $hasMarker,
        ];

        Log::info('IsConflictResolvedUseCase: 完了', [
            'filename' => $filename,
            'result' => $result
        ]);

        return $result;
    }
}
