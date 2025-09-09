<?php

namespace App\UseCases\DocumentCategory;

use App\Dto\UseCase\DocumentCategory\CreateDocumentCategoryDto;
use App\Enums\EditStartVersionTargetType;
use App\Models\DocumentCategory;
use App\Models\EditStartVersion;
use App\Models\Organization;
use App\Models\PullRequestEditSession;
use App\Models\PullRequestEditSessionDiff;
use App\Services\UserBranchService;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Support\Facades\DB;

class CreateDocumentCategoryUseCase
{
    public function __construct(
        private UserBranchService $userBranchService,
    ) {}

    /**
     * ドキュメントカテゴリを作成
     *
     * @param CreateDocumentCategoryDto $dto カテゴリ作成DTO
     * @param object $user 認証済みユーザー
     * @return object 作成されたカテゴリ
     */
    public function execute(CreateDocumentCategoryDto $dto, object $user): object
    {
        try {
            DB::beginTransaction();

            $organizationId = $user->organization_id;
            $organization = Organization::find($organizationId);
            if (! $organization) {
                throw new NotFoundException();
            }

            // ユーザーブランチIDを取得または作成
            $userBranchId = $this->userBranchService->fetchOrCreateActiveBranch(
                $user,
                $dto->editPullRequestId
            );

            // プルリクエスト編集セッションIDを取得
            $pullRequestEditSessionId = PullRequestEditSession::findEditSessionId($dto->editPullRequestId, $dto->pullRequestEditToken, $user->id);

            // カテゴリを作成
            $category = DocumentCategory::create([
                'title' => $dto->title,
                'description' => $dto->description,
                'user_branch_id' => $userBranchId,
                'pull_request_edit_session_id' => $pullRequestEditSessionId,
                'parent_id' => $dto->parentId,
                'organization_id' => $organizationId,
            ]);

            // EditStartVersionを作成
            EditStartVersion::create([
                'user_branch_id' => $userBranchId,
                'target_type' => EditStartVersionTargetType::CATEGORY->value,
                'original_version_id' => $category->id,
                'current_version_id' => $category->id,
            ]);

            // プルリクエスト編集セッション差分の処理
            if ($pullRequestEditSessionId) {
                PullRequestEditSessionDiff::updateOrCreate(
                    [
                        'pull_request_edit_session_id' => $pullRequestEditSessionId,
                        'target_type' => EditStartVersionTargetType::CATEGORY->value,
                        'current_version_id' => $category->id,
                    ],
                    [
                        'current_version_id' => $category->id,
                        'diff_type' => 'created',
                    ]
                );
            }

            DB::commit();

            return $category;

        } catch (\Exception $e) {
            DB::rollBack();

            throw $e;
        }
    }
}
