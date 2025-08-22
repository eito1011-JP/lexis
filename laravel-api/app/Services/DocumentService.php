<?php

namespace App\Services;

use App\Constants\DocumentCategoryConstants;
use App\Enums\DocumentStatus;
use App\Models\DocumentVersion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DocumentService
{
    public function __construct(
        private DocumentCategoryService $documentCategoryService
    ) {}

    /**
     * file_orderの重複処理・自動採番を行う
     *
     * @param  int|null  $requestedFileOrder  リクエストされたfile_order
     * @param  int|null  $categoryId  カテゴリID
     * @return int 正規化されたfile_order
     */
    public function normalizeFileOrder(?int $requestedFileOrder, ?int $categoryId = null): int
    {
        $targetCategoryId = $categoryId ?? DocumentCategoryConstants::DEFAULT_CATEGORY_ID;

        if ($requestedFileOrder) {
            // file_order重複時、既存のfile_order >= 入力値を+1してずらす
            DocumentVersion::where('category_id', $targetCategoryId)
                ->where('status', 'merged')
                ->where('file_order', '>=', $requestedFileOrder)
                ->where('is_deleted', 0)
                ->increment('file_order');

            return $requestedFileOrder;
        } else {
            // file_order未入力時、カテゴリ内最大値+1をセット
            $maxOrder = DocumentVersion::where('category_id', $targetCategoryId)
                ->max('file_order') ?? 0;

            return $maxOrder + 1;
        }
    }

    /**
     * ドキュメントファイルパスを生成
     *
     * @param  string  $categoryPath  カテゴリパス
     * @param  string  $slug  スラッグ
     */
    public function generateDocumentFilePath(string $categoryPath, string $slug): string
    {
        $categoryPath = $categoryPath ? trim($categoryPath, '/') : '';

        return $categoryPath ? "{$categoryPath}/{$slug}.md" : "{$slug}.md";
    }

    /**
     * file_orderの処理と他のドキュメントの順序調整
     *
     * @param  mixed  $fileOrder  リクエストされたfile_order
     * @param  int  $categoryId  カテゴリID
     * @param  int  $oldFileOrder  既存のfile_order
     * @param  int  $userBranchId  ユーザーブランチID
     * @param  int  $excludeId  除外するドキュメントID
     * @return int 最終的なfile_order
     */
    public function processFileOrder($fileOrder, int $categoryId, int $oldFileOrder, int $userBranchId, int $excludeId): int
    {
        // file_orderが空の場合は最大値+1を設定
        if (empty($fileOrder) && $fileOrder !== 0) {
            $maxOrder = DocumentVersion::where('category_id', $categoryId)
                ->where('status', DocumentStatus::MERGED->value)
                ->max('file_order');

            return ($maxOrder ?? 0) + 1;
        }

        $newFileOrder = (int) $fileOrder;

        // file_orderが変更された場合のみ他のドキュメントを調整
        if ($newFileOrder !== $oldFileOrder) {
            $this->adjustOtherDocumentsOrder($categoryId, $newFileOrder, $oldFileOrder, $userBranchId, $excludeId);
        }

        return $newFileOrder;
    }

    /**
     * 他のドキュメントの順序を調整
     *
     * @param  int  $categoryId  カテゴリID
     * @param  int  $newFileOrder  新しいfile_order
     * @param  int  $oldFileOrder  古いfile_order
     * @param  int  $userBranchId  ユーザーブランチID
     * @param  int  $excludeId  除外するドキュメントID
     */
    private function adjustOtherDocumentsOrder(int $categoryId, int $newFileOrder, int $oldFileOrder, int $userBranchId, int $excludeId): void
    {
        $documentsToShift = DocumentVersion::where('category_id', $categoryId)
            ->where(function ($query) use ($userBranchId) {
                $query->where('status', DocumentStatus::MERGED->value)
                    ->orWhere('user_branch_id', $userBranchId);
            })
            ->where('id', '!=', $excludeId);

        if ($newFileOrder < $oldFileOrder) {
            // 上に移動する場合：新しい位置以上、元の位置未満の範囲のレコードを+1
            $documentsToShift = $documentsToShift
                ->where('file_order', '>=', $newFileOrder)
                ->where('file_order', '<', $oldFileOrder)
                ->orderBy('file_order', 'asc');
        } else {
            // 下に移動する場合：元の位置超過、新しい位置以下の範囲のレコードを-1
            $documentsToShift = $documentsToShift
                ->where('file_order', '>', $oldFileOrder)
                ->where('file_order', '<=', $newFileOrder)
                ->orderBy('file_order', 'asc');
        }

        $documents = $documentsToShift->get();

        foreach ($documents as $doc) {
            $newOrder = $newFileOrder < $oldFileOrder ? $doc->file_order + 1 : $doc->file_order - 1;

            // 新しいバージョンを作成して順序を更新
            DocumentVersion::create([
                'user_id' => $doc->user_id,
                'user_branch_id' => $userBranchId,
                'file_path' => $doc->file_path,
                'status' => DocumentStatus::DRAFT->value,
                'content' => $doc->content,
                'slug' => $doc->slug,
                'sidebar_label' => $doc->sidebar_label,
                'file_order' => $newOrder,
                'last_edited_by' => $doc->last_edited_by,
                'is_deleted' => 0,
                'is_public' => $doc->is_public,
                'category_id' => $doc->category_id,
            ]);

            // 元のバージョンは論理削除しない
        }
    }

    /**
     * カテゴリのドキュメントを取得（ブランチ別）
     */
    public function fetchDocumentsByCategoryId(
        int $categoryId,
        ?int $userBranchId = null,
        ?int $editPullRequestId = null
    ): Collection {

        $base = DocumentVersion::query()
            ->select(
                'id',
                'user_branch_id',
                'category_id',
                'sidebar_label',
                'slug',
                'is_public',
                'status',
                'last_edited_by',
                'file_order'
            )
            ->where('category_id', $categoryId);

        // 同一ブランチにおいて、より新しいバージョンに置き換えられた「古い元版」を除外
        // current_version_id が自分自身ではない original_version を隠す
        if (! is_null($userBranchId)) {
            $base->whereNotExists(function ($query) use ($userBranchId) {
                $query->select(DB::raw(1))
                    ->from('edit_start_versions as e')
                    ->whereColumn('e.original_version_id', 'document_versions.id')
                    ->where('e.target_type', 'document')
                    ->whereColumn('e.current_version_id', '!=', 'document_versions.id')
                    ->where('e.user_branch_id', $userBranchId);
            });
        }

        // 参照のみ（user_branch_id なし）の場合
        if (is_null($userBranchId)) {
            return $base
                ->where('status', DocumentStatus::MERGED->value)
                ->where(function ($q) {
                    // 本線（user_branch_id が null）は常に表示
                    $q->whereNull('user_branch_id')
                        // ブランチに紐づく MERGED は、そのブランチに PR が存在しない場合のみ表示
                        ->orWhereNotExists(function ($sub) {
                            $sub->select(DB::raw(1))
                                ->from('pull_requests as pr')
                                ->whereColumn('pr.user_branch_id', 'document_versions.user_branch_id');
                        });
                })
                ->get();
        }

        // 再編集時（user_branch_id && edit_pull_request_id が存在）
        if ($userBranchId && $editPullRequestId) {
            $base->where(function ($q) use ($userBranchId, $editPullRequestId, $categoryId) {
                // このPRに紐づく「このブランチ」の作業中（DRAFT/PUSHED）
                $q->orWhere(function ($q1) use ($userBranchId, $editPullRequestId) {
                    $q1->where('user_branch_id', $userBranchId)
                        ->whereIn('status', [DocumentStatus::DRAFT->value, DocumentStatus::PUSHED->value])
                        ->whereIn('pull_request_edit_session_id', function ($sessionQuery) use ($editPullRequestId, $userBranchId) {
                            $sessionQuery->select('s.id')
                                ->from('pull_request_edit_sessions as s')
                                ->join('pull_requests as pr', 's.pull_request_id', '=', 'pr.id')
                                ->where('s.pull_request_id', $editPullRequestId)
                                ->where('pr.user_branch_id', $userBranchId);
                        });
                });

                // 閲覧用の安定版（MERGED）。作業中と同じ slug は隠す（衝突回避）
                $q->orWhere(function ($q2) use ($userBranchId, $editPullRequestId, $categoryId) {
                    $q2->where('status', DocumentStatus::MERGED->value)
                        ->where(function ($q2a) use ($userBranchId) {
                            $q2a->whereNull('user_branch_id')
                                ->orWhere('user_branch_id', '<>', $userBranchId);
                        })
                        // 同一PRで作業中の slug は隠す
                        ->whereNotIn('slug', function ($slugQuery) use ($userBranchId, $editPullRequestId, $categoryId) {
                            $slugQuery->select('d2.slug')
                                ->from('document_versions as d2')
                                ->where('d2.user_branch_id', $userBranchId)
                                ->where('d2.category_id', $categoryId)
                                ->whereIn('d2.status', [DocumentStatus::DRAFT->value, DocumentStatus::PUSHED->value])
                                ->whereIn('d2.pull_request_edit_session_id', function ($sessionQuery2) use ($editPullRequestId, $userBranchId) {
                                    $sessionQuery2->select('s.id')
                                        ->from('pull_request_edit_sessions as s')
                                        ->join('pull_requests as pr', 's.pull_request_id', '=', 'pr.id')
                                        ->where('s.pull_request_id', $editPullRequestId)
                                        ->where('pr.user_branch_id', $userBranchId);
                                });
                        })
                        // 同一ブランチ上の（PR外含む）作業中 slug も隠す（再編集前のPUSHED を優先表示するため）
                        ->whereNotIn('slug', function ($slugQuery) use ($userBranchId, $categoryId) {
                            $slugQuery->select('d3.slug')
                                ->from('document_versions as d3')
                                ->where('d3.user_branch_id', $userBranchId)
                                ->where('d3.category_id', $categoryId)
                                ->whereIn('d3.status', [DocumentStatus::DRAFT->value, DocumentStatus::PUSHED->value]);
                        });
                });

                // このPRに紐づかない「同一ブランチ上のPUSHED」（再編集前の状態）。
                // ただし、当該PRで作業中の slug があれば隠す
                $q->orWhere(function ($q3) use ($userBranchId, $editPullRequestId, $categoryId) {
                    $q3->where('user_branch_id', $userBranchId)
                        ->where('status', DocumentStatus::PUSHED->value)
                        ->where(function ($q3a) use ($editPullRequestId, $userBranchId) {
                            $q3a->whereNull('pull_request_edit_session_id')
                                ->orWhereNotIn('pull_request_edit_session_id', function ($sessionQuery) use ($editPullRequestId, $userBranchId) {
                                    $sessionQuery->select('s.id')
                                        ->from('pull_request_edit_sessions as s')
                                        ->join('pull_requests as pr', 's.pull_request_id', '=', 'pr.id')
                                        ->where('s.pull_request_id', $editPullRequestId)
                                        ->where('pr.user_branch_id', $userBranchId);
                                });
                        })
                        ->whereNotIn('slug', function ($slugQuery) use ($userBranchId, $editPullRequestId, $categoryId) {
                            $slugQuery->select('d2.slug')
                                ->from('document_versions as d2')
                                ->where('d2.user_branch_id', $userBranchId)
                                ->where('d2.category_id', $categoryId)
                                ->whereIn('d2.status', [DocumentStatus::DRAFT->value, DocumentStatus::PUSHED->value])
                                ->whereIn('d2.pull_request_edit_session_id', function ($sessionQuery2) use ($editPullRequestId, $userBranchId) {
                                    $sessionQuery2->select('s.id')
                                        ->from('pull_request_edit_sessions as s')
                                        ->join('pull_requests as pr', 's.pull_request_id', '=', 'pr.id')
                                        ->where('s.pull_request_id', $editPullRequestId)
                                        ->where('pr.user_branch_id', $userBranchId);
                                });
                        });
                });
            });
        }
        // 初回編集時（user_branch_id のみ存在）
        else {
            $base->where(function ($q) use ($userBranchId) {
                // 自ブランチの編集中
                $q->orWhere(function ($q1) use ($userBranchId) {
                    $q1->where('user_branch_id', $userBranchId)
                        ->where('status', DocumentStatus::DRAFT->value);
                });

                // 他ブランチ/本線の安定版。自ブランチと同じ slug は隠す
                $q->orWhere(function ($q2) use ($userBranchId) {
                    $q2->where('status', DocumentStatus::MERGED->value)
                        ->where(function ($q2a) use ($userBranchId) {
                            $q2a->whereNull('user_branch_id')
                                ->orWhere('user_branch_id', '<>', $userBranchId);
                        })
                        ->whereNotIn('slug', function ($slugQuery) use ($userBranchId) {
                            $slugQuery->select('d2.slug')
                                ->from('document_versions as d2')
                                ->where('d2.user_branch_id', $userBranchId)
                                ->whereIn('d2.status', [DocumentStatus::DRAFT->value, DocumentStatus::PUSHED->value]);
                        });
                });
            });
        }

        return $base->get();
    }
}
