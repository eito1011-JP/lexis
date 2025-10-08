<?php

namespace App\Enums;

enum PullRequestActivityAction: string
{
    case FIX_REQUEST_SENT = 'fix_request_sent';
    case FIX_REQUEST_APPLIED = 'fix_request_applied';
    case ASSIGNED_REVIEWER = 'assigned_reviewer';
    case REVIEWER_APPROVED = 'reviewer_approved';
    case COMMENTED = 'commented';
    case PULL_REQUEST_MERGED = 'pull_request_merged';
    case PULL_REQUEST_CLOSED = 'pull_request_closed';
    case PULL_REQUEST_REOPENED = 'pull_request_reopened';
    case PULL_REQUEST_TITLE_EDITED = 'pull_request_title_edited';

    /**
     * 日本語での表示名を取得
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::FIX_REQUEST_SENT => '修正リクエストが送信されました',
            self::FIX_REQUEST_APPLIED => '修正リクエストが適用されました',
            self::ASSIGNED_REVIEWER => 'レビュワーが設定されました',
            self::REVIEWER_APPROVED => '変更提案が承認されました',
            self::COMMENTED => 'コメントが投稿されました',
            self::PULL_REQUEST_MERGED => 'プルリクエストがマージされました',
            self::PULL_REQUEST_CLOSED => 'プルリクエストがクローズされました',
            self::PULL_REQUEST_REOPENED => 'プルリクエストが再オープンされました',
            self::PULL_REQUEST_TITLE_EDITED => 'タイトルが編集されました',
        };
    }

    /**
     * 全ての値を取得
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
