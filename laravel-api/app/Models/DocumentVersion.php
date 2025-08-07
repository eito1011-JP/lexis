<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\FixRequestStatus;
use App\Traits\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class DocumentVersion extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'document_versions';

    protected $fillable = [
        'user_id',
        'user_branch_id',
        'pull_request_edit_session_id',
        'file_path',
        'status',
        'content',
        'original_blob_sha',
        'slug',
        'category_id',
        'sidebar_label',
        'file_order',
        'last_edited_by',
        'last_reviewed_by',
        'is_deleted',
        'deleted_at',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'file_order' => 'integer',
        'is_deleted' => 'boolean',
    ];

    /**
     * カテゴリのドキュメントを取得（ブランチ別）
     */
    public static function getDocumentsByCategoryId(int $categoryId, ?int $userBranchId = null, ?int $editPullRequestId = null): \Illuminate\Database\Eloquent\Collection
    {
        return self::select('id', 'user_branch_id', 'category_id', 'sidebar_label', 'slug', 'is_public', 'status', 'last_edited_by', 'file_order')
            ->when($editPullRequestId, function ($query, $editPullRequestId) {
                $query->orWhere(function ($subQ) use ($editPullRequestId) {
                    $subQ->whereHas('userBranch.pullRequests', function ($prQ) use ($editPullRequestId) {
                        $prQ->where('id', $editPullRequestId);
                    })
                        ->where('status', DocumentStatus::PUSHED->value);
                });
            })
            ->when($userBranchId, function ($query, $userBranchId) use ($categoryId) {
                // 指定されたブランチの下書きドキュメントを取得
                $query->orWhere(function ($subQ) use ($userBranchId) {
                    $subQ->where('status', DocumentStatus::DRAFT->value)
                        ->where('user_branch_id', $userBranchId)
                        ->whereNot('status', DocumentStatus::PUSHED->value);
                });

                // 指定されたブランチのmergedドキュメントを取得（EditStartVersionのoriginal_document_idと一致するものは除外）
                $excludedOriginalDocumentIds = EditStartVersion::where('user_branch_id', $userBranchId)
                    ->where('target_type', 'document')
                    ->whereNotNull('original_version_id')
                    ->pluck('original_version_id')
                    ->toArray();

                $query->orWhere(function ($subQ) use ($excludedOriginalDocumentIds) {
                    $subQ->where('status', DocumentStatus::MERGED->value)
                        ->whereNot('status', DocumentStatus::PUSHED->value);
                    Log::info('syv: '.json_encode($excludedOriginalDocumentIds));
                    if (! empty($excludedOriginalDocumentIds)) {
                        Log::info('excludedOriginalDocumentIds: '.json_encode($excludedOriginalDocumentIds));
                        $subQ->whereNotIn('id', $excludedOriginalDocumentIds);
                    }
                });

                $appliedFixRequestDocumentIds = FixRequest::where('status', FixRequestStatus::APPLIED->value)
                    ->whereNotNull('document_version_id')
                    ->whereHas('documentVersion', function ($q) use ($userBranchId, $categoryId) {
                        $q->where('user_branch_id', $userBranchId)
                            ->where('category_id', $categoryId);
                    })
                    ->pluck('document_version_id')
                    ->toArray();

                if (! empty($appliedFixRequestDocumentIds)) {
                    $query->orWhereIn('id', $appliedFixRequestDocumentIds);
                }
            })
            ->when(! $userBranchId, function ($query) {
                $query->where('status', DocumentStatus::MERGED->value);
            })
            ->where('category_id', $categoryId)
            ->get();
    }

    /**
     * カテゴリとのリレーション
     */
    public function category()
    {
        return $this->belongsTo(DocumentCategory::class, 'category_id');
    }

    /**
     * ユーザーブランチとのリレーション
     */
    public function userBranch()
    {
        return $this->belongsTo(UserBranch::class, 'user_branch_id');
    }

    /**
     * ユーザーとのリレーション
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 編集開始バージョンとのリレーション（オリジナルバージョンとして）
     */
    public function originalEditStartVersions()
    {
        return $this->hasMany(EditStartVersion::class, 'original_version_id');
    }

    /**
     * 編集開始バージョンとのリレーション（現在のバージョンとして）
     */
    public function currentEditStartVersions()
    {
        return $this->hasMany(EditStartVersion::class, 'current_version_id');
    }

    /**
     * プルリクエスト編集セッションとのリレーション
     */
    public function pullRequestEditSession()
    {
        return $this->belongsTo(PullRequestEditSession::class, 'pull_request_edit_session_id');
    }

    /**
     * カテゴリパスを取得
     */
    public function getCategoryPathAttribute(): ?string
    {
        if (! $this->category) {
            return null;
        }

        return $this->category->parent_path;
    }
}
