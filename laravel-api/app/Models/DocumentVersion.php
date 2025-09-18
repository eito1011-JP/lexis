<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Traits\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentVersion extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'document_versions';

    protected $fillable = [
        'user_id',
        'user_branch_id',
        'pull_request_edit_session_id',
        'organization_id',
        'status',
        'content',
        'category_id',
        'title',
        'description',
        'last_edited_by',
        'last_reviewed_by',
        'is_deleted',
        'deleted_at',
    ];

    protected $casts = [
        'organization_id' => 'integer',
        'is_deleted' => 'boolean',
    ];

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
     * 組織とのリレーション
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
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

    /**
     * ステータスによるスコープ
     */
    public function scopeForStatus($query, int $status)
    {
        return $query->where('status', $status);
    }

    /**
     * カテゴリIDによるスコープ
     */
    public function scopeByCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * 並べ替え対象のスコープを組み立て
     * - PR未提出: MERGED + (自ブランチの DRAFT)
     * - 編集セッション: MERGED + (自ブランチの DRAFT/PUSHED)
     */
    public function scopeForOrdering($query, int $categoryId, int $userBranchId, ?int $editPullRequestId)
    {
        $isEditSession = ! empty($editPullRequestId);

        return $query->where('category_id', $categoryId)
            ->where(function ($q) use ($userBranchId, $isEditSession) {
                $q->where('status', DocumentStatus::MERGED->value)
                    ->orWhere(function ($q2) use ($userBranchId, $isEditSession) {
                        $statuses = $isEditSession
                            ? [DocumentStatus::DRAFT->value, DocumentStatus::PUSHED->value]
                            : [DocumentStatus::DRAFT->value];

                        $q2->where('user_branch_id', $userBranchId)
                            ->whereIn('status', $statuses);
                    });
            });
    }
}
