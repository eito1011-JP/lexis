<?php

namespace App\Http\Controllers\Api;

use App\Consts\ErrorType;
use App\Dto\UseCase\Explorer\FetchNodesDto;
use App\Http\Requests\Api\Explorer\FetchNodesRequest;
use App\UseCases\Explorer\FetchNodesUseCase;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Psr\Log\LogLevel;

class ExplorerController extends ApiBaseController
{
    protected FetchNodesUseCase $fetchNodesUseCase;

    public function __construct(FetchNodesUseCase $fetchNodesUseCase)
    {
        $this->fetchNodesUseCase = $fetchNodesUseCase;
    }

    /**
     * 指定したカテゴリに従属するカテゴリとドキュメントを取得
     */
    public function fetchNodes(FetchNodesRequest $request, FetchNodesUseCase $useCase): JsonResponse
    {
        try {
            // 認証ユーザーを取得
            $loginUser = $this->user();

            if (! $loginUser) {
                return $this->sendError(
                    ErrorType::CODE_AUTHENTICATION_FAILED,
                    '認証されていません',
                    ErrorType::STATUS_AUTHENTICATION_FAILED,
                    LogLevel::WARNING
                );
            }

            // バリデート済みリクエストデータを取得
            $validatedData = $request->validated();

            // DTOを作成
            $dto = FetchNodesDto::fromRequest($validatedData);

            // UseCaseを実行
            $result = $useCase->execute($dto, $loginUser);

            return response()->json([
                'documents' => $result['documents'],
                'categories' => $result['categories'],
            ]);

        } catch (Exception $e) {
            Log::error($e);

            return $this->sendError(
                ErrorType::CODE_INTERNAL_ERROR,
                'サーバーエラーが発生しました',
                ErrorType::STATUS_INTERNAL_ERROR,
                LogLevel::ERROR
            );
        }
    }
}
