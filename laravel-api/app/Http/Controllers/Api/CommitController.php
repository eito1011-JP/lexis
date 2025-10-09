<?php

namespace App\Http\Controllers\Api;

use App\Consts\ErrorType;
use App\Dto\UseCase\Commit\CreateCommitDto;
use App\Exceptions\AuthenticationException;
use App\Exceptions\NotFoundException;
use App\Http\Requests\Api\Commit\StoreRequest;
use App\UseCases\Commit\CreateCommitUseCase;
use Illuminate\Http\JsonResponse;
use Psr\Log\LogLevel;

/**
 * コミットコントローラー
 */
class CommitController extends ApiBaseController
{
    protected CreateCommitUseCase $createCommitUseCase;

    /**
     * コンストラクタ
     *
     * @param  CreateCommitUseCase  $createCommitUseCase  コミット作成UseCase
     */
    public function __construct(CreateCommitUseCase $createCommitUseCase)
    {
        $this->createCommitUseCase = $createCommitUseCase;
    }

    /**
     * コミット作成
     *
     * @param  StoreRequest  $request  リクエスト
     */
    public function store(StoreRequest $request): JsonResponse
    {
        try {
            // 1. 認証ユーザーか確認
            $user = $this->user();

            if (! $user) {
                return $this->sendError(
                    ErrorType::CODE_AUTHENTICATION_FAILED,
                    __('errors.MSG_AUTHENTICATION_FAILED'),
                    ErrorType::STATUS_AUTHENTICATION_FAILED,
                );
            }

            // 2. DTOを作成
            $dto = new CreateCommitDto(
                pullRequestId: $request->pull_request_id,
                message: $request->message,
            );

            // 3. UseCaseを実行
            $this->createCommitUseCase->execute($dto, $user);

            // 4. レスポンスを返す
            return response()->json();

        } catch (NotFoundException $e) {
            return $this->sendError(
                ErrorType::CODE_NOT_FOUND,
                $e->getMessage(),
                ErrorType::STATUS_NOT_FOUND,
            );
        } catch (AuthenticationException $e) {
            return $this->sendError(
                ErrorType::CODE_AUTHENTICATION_FAILED,
                $e->getMessage(),
                ErrorType::STATUS_AUTHENTICATION_FAILED,
            );
        } catch (\Exception $e) {
            return $this->sendError(
                ErrorType::CODE_INTERNAL_ERROR,
                __('errors.MSG_COMMIT_CREATE_FAILED'),
                ErrorType::STATUS_INTERNAL_ERROR,
                LogLevel::ERROR,
            );
        }
    }
}
