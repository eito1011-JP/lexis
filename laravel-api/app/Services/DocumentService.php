<?php

namespace App\Services;

use App\Constants\DocumentCategoryConstants;
use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\User;
use App\Models\UserBranch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DocumentService
{
    public function __construct(
        private CategoryService $CategoryService
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
     * file_order の並べ替えに伴い、影響を受ける「他の」ドキュメントについて
     * 新規の DocumentVersion(DRAFT) を作成し、EditStartVersion を記録する。
     *
     * @param  int  $newFileOrder  リクエストされた file_order（1始まり）
     * @param  int  $oldFileOrder  既存の file_order
     * @param  int  $categoryId  カテゴリID
     * @param  int  $userBranchId  編集中のユーザーブランチID
     * @param  int|null  $editPullRequestId  編集対象のPR ID（null/0なら PR 未提出モード）
     * @param  int  $excludeDocumentId  対象本人の DocumentVersion ID（並び替えから除外）
     * @param  int  $actorUserId  この操作を行うユーザーID（差分版の作成者）
     * @param  string  $actorEmail  この操作を行うユーザーのメール（last_edited_by に入れる）
     * @return int クランプ後の最終 new file_order（呼び出し側で対象本人にセットして使う）
     */
    public function updateFileOrder(
        int $newFileOrder,
        int $oldFileOrder,
        int $categoryId,
        int $userBranchId,
        ?int $editPullRequestId,
        int $excludeDocumentId,
        int $actorUserId,
        string $actorEmail
    ): int {
        // 対象集合（スコープ）を構築
        $base = DocumentVersion::forOrdering($categoryId, $userBranchId, $editPullRequestId);

        // new をスコープの最大にクランプ（1..max）
        $maxOrder = (int) (clone $base)->max('file_order') ?: 0;
        if ($maxOrder <= 0) {
            // 並べ替え対象がそもそも居ない：変更なし扱い
            return $oldFileOrder;
        }
        $finalFileOrder = max(1, min($newFileOrder, $maxOrder));

        // 変更なし → 何も作らない
        if ($finalFileOrder === $oldFileOrder) {
            return $oldFileOrder;
        }

        $isMovingUp = $finalFileOrder < $oldFileOrder;

        DB::transaction(function () use ($base, $isMovingUp, $finalFileOrder, $oldFileOrder, $excludeDocumentId, $userBranchId, $actorUserId, $actorEmail) {
            // 影響範囲の抽出（対象本人除外）
            $q = (clone $base)->where('id', '!=', $excludeDocumentId);

            if ($isMovingUp) {
                // 上へ： [new, old) を +1
                $q->where('file_order', '>=', $finalFileOrder)
                    ->where('file_order', '<',  $oldFileOrder)
                    ->orderBy('file_order', 'asc');
            } else {
                // 下へ： (old, new] を -1
                $q->where('file_order', '>',  $oldFileOrder)
                    ->where('file_order', '<=', $finalFileOrder)
                    ->orderBy('file_order', 'asc');
            }

            $affected = $q->get();

            // memo: 本当はforeachではなくbulk insertするようにしたい
            foreach ($affected as $doc) {
                $shiftedOrder = $isMovingUp ? $doc->file_order + 1 : $doc->file_order - 1;

                // 影響ドキュメントの「差分版（DRAFT）」を新規作成
                $newVersion = DocumentVersion::create([
                    'user_id' => $actorUserId,       // 差分を作った操作者として記録
                    'user_branch_id' => $userBranchId,      // 編集中ブランチに紐づく
                    'pull_request_edit_session_id' => null,               // 必要なら呼出側で追加
                    'file_path' => $doc->file_path,
                    'status' => DocumentStatus::DRAFT->value,
                    'content' => $doc->content,
                    'slug' => $doc->slug,
                    'sidebar_label' => $doc->sidebar_label,
                    'file_order' => $shiftedOrder,
                    'last_edited_by' => $actorEmail,
                    'is_public' => $doc->is_public,
                    'category_id' => $doc->category_id,
                ]);

                // EditStartVersion を記録（“元”と“新規差分”の対応）
                EditStartVersion::create([
                    'user_branch_id' => $userBranchId,
                    'target_type' => EditStartVersionTargetType::DOCUMENT->value,
                    'original_version_id' => $doc->id,
                    'current_version_id' => $newVersion->id,
                ]);
            }
        });

        // 対象本人の新バージョンのfile_orderを返却
        return $finalFileOrder;
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

    /**
     * 作業コンテキストに応じて適切なドキュメントを取得
     */
    public function getDocumentByWorkContext(
        int $documentEntityId,
        User $user,
        ?string $pullRequestEditSessionToken = null
    ): ?DocumentVersion {
        // ユーザーのアクティブブランチを取得
        $activeUserBranch = UserBranch::where('user_id', $user->id)->active()->first();

        $baseQuery = DocumentVersion::where('entity_id', $documentEntityId)
            ->where('organization_id', $user->organizationMember->organization_id);

        if (! $activeUserBranch) {
            // アクティブなユーザーブランチがない場合：MERGEDステータスのみ取得
            return $baseQuery->where('status', DocumentStatus::MERGED->value)->first();
        }

        // EditStartVersionから最新の現在のバージョンIDを取得
        // 同じentity_idに対するEditStartVersionチェーンの最新のcurrent_version_idを取得
        $editStartVersions = EditStartVersion::where('user_branch_id', $activeUserBranch->id)
            ->where('target_type', EditStartVersionTargetType::DOCUMENT->value)
            ->whereHas('originalDocumentVersion', function ($query) use ($documentEntityId) {
                $query->where('entity_id', $documentEntityId);
            })
            ->orWhere(function ($query) use ($activeUserBranch, $documentEntityId) {
                $query->where('user_branch_id', $activeUserBranch->id)
                    ->where('target_type', EditStartVersionTargetType::DOCUMENT->value)
                    ->whereHas('currentDocumentVersion', function ($subQuery) use ($documentEntityId) {
                        $subQuery->where('entity_id', $documentEntityId);
                    });
            })
            ->orderBy('id', 'desc')
            ->get();

        if ($editStartVersions->isNotEmpty()) {
            // 最新のEditStartVersionのcurrent_version_idを取得
            $latestEditStartVersion = $editStartVersions->first();
            $document = $baseQuery->where('id', $latestEditStartVersion->current_version_id)->first();
            if ($document) {
                return $document;
            }
        }

        if ($pullRequestEditSessionToken) {
            // 再編集している場合：PUSHEDとDRAFTとMERGEDステータスを取得（最新のものを優先）
            return $baseQuery->where(function ($query) use ($activeUserBranch) {
                $query->where(function ($q1) use ($activeUserBranch) {
                    $q1->whereIn('status', [
                        DocumentStatus::PUSHED->value,
                        DocumentStatus::DRAFT->value,
                    ])
                        ->where('user_branch_id', $activeUserBranch->id);
                })->orWhere('status', DocumentStatus::MERGED->value);
            })->orderBy('created_at', 'desc')->first();
        } else {
            // 初回編集の場合：DRAFTとMERGEDステータスを取得（最新のものを優先）
            return $baseQuery->where(function ($query) use ($activeUserBranch) {
                $query->where(function ($q1) use ($activeUserBranch) {
                    $q1->where('status', DocumentStatus::DRAFT->value)
                        ->where('user_branch_id', $activeUserBranch->id);
                })->orWhere('status', DocumentStatus::MERGED->value);
            })->orderBy('created_at', 'desc')->first();
        }
    }
}
