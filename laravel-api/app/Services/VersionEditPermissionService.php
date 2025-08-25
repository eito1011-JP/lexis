<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Models\DocumentVersion;
use App\Models\PullRequestEditSession;

/**
 * バージョン編集権限確認サービス
 * 
 * 編集セッションの取得と編集権限の確認を統合した汎用的なサービス
 */
class VersionEditPermissionService
{
    /**
     * 編集権限をチェックし、編集セッションIDを取得する
     *
     * @param DocumentVersion $existingDocument 既存のドキュメント
     * @param int $userBranchId ユーザーブランチID
     * @param object $user 認証済みユーザー
     * @param int|null $editPullRequestId 編集プルリクエストID
     * @param string|null $pullRequestEditToken プルリクエスト編集トークン
     * @return array 編集権限の結果とセッションID
     * @throws \InvalidArgumentException 権限がない場合や無効なセッション
     */
    public function hasEditPermission(
        DocumentVersion $existingDocument,
        int $userBranchId,
        object $user,
        ?int $editPullRequestId = null,
        ?string $pullRequestEditToken = null
    ): array {
        // 編集セッションが指定されている場合のみ処理
        $pullRequestEditSessionId = null;
        $hasReEditSession = $editPullRequestId && $pullRequestEditToken;
        
        if ($hasReEditSession) {
            $pullRequestEditSessionId = $this->findEditSession($user, $editPullRequestId, $pullRequestEditToken);
        }

        // 編集権限チェック
        if (!$this->canEditDocument($existingDocument, $userBranchId, $pullRequestEditSessionId)) {
            throw new \InvalidArgumentException('他のユーザーの未マージドキュメントは編集できません');
        }

        return [
            'can_edit' => true,
            'has_re_edit_session' => $hasReEditSession,
            'pull_request_edit_session_id' => $pullRequestEditSessionId,
        ];
    }

    /**
     * 編集セッションを検索してIDを取得
     *
     * @param object $user 認証済みユーザー
     * @param int $editPullRequestId 編集プルリクエストID
     * @param string $pullRequestEditToken プルリクエスト編集トークン
     * @return int 編集セッションID
     * @throws \InvalidArgumentException 無効なセッション
     */
    private function findEditSession(
        object $user,
        int $editPullRequestId,
        string $pullRequestEditToken
    ): int {
        $validSession = PullRequestEditSession::findValidSession(
            $editPullRequestId,
            $pullRequestEditToken,
            $user->id
        );

        if (!$validSession) {
            throw new \InvalidArgumentException('無効な編集セッションです');
        }

        return $validSession->id;
    }

    /**
     * ドキュメントの編集権限をチェック
     *
     * @param DocumentVersion $existingDocument 既存のドキュメント
     * @param int $userBranchId ユーザーブランチID
     * @param int|null $pullRequestEditSessionId プルリクエスト編集セッションID
     * @return bool 編集可能かどうか
     */
    public function canEditDocument(
        DocumentVersion $existingDocument,
        int $userBranchId,
        ?int $pullRequestEditSessionId = null
    ): bool {
        // MERGEDドキュメントは常に編集可能
        if ($existingDocument->status === DocumentStatus::MERGED->value) {
            return true;
        }

        // 同じユーザーブランチの場合
        if ($existingDocument->user_branch_id === $userBranchId) {
            // 編集セッションが存在する場合：DRAFT または PUSHED ステータスで編集可能
            if ($pullRequestEditSessionId) {
                return in_array($existingDocument->status, [
                    DocumentStatus::DRAFT->value,
                    DocumentStatus::PUSHED->value
                ], true);
            }
            
            // 編集セッションが存在しない場合：DRAFT ステータスのみ編集可能
            return $existingDocument->status === DocumentStatus::DRAFT->value;
        }

        // 他のユーザーのドキュメントは編集不可
        return false;
    }
}
