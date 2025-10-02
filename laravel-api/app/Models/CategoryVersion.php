<?php

namespace App\Models;

use App\Enums\DocumentCategoryStatus;
use App\Enums\FixRequestStatus;
use App\Traits\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoryVersion extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'category_versions';

    protected $fillable = [
        'entity_id',
        'parent_entity_id',
        'title',
        'description',
        'status',
        'user_branch_id',
        'pull_request_edit_session_id',
        'is_deleted',
        'organization_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'position' => 'integer',
        'is_deleted' => 'boolean',
    ];

    /**
     * サブカテゴリを取得（ブランチ別）
     */
    public static function getSubCategories(int $parentId, ?int $userBranchId = null, ?int $editPullRequestId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = self::select('title', 'position')
            ->where('parent_entity_id', $parentId)
            ->where(function ($q) use ($userBranchId) {
                $q->where('status', 'merged')
                    ->orWhere(function ($subQ) use ($userBranchId) {
                        $subQ->where('user_branch_id', $userBranchId)
                            ->where('status', DocumentCategoryStatus::DRAFT->value);
                    });
            })
            ->when($editPullRequestId, function ($query, $editPullRequestId) {
                return $query->orWhere(function ($subQ) use ($editPullRequestId) {
                    $subQ->whereHas('userBranch.pullRequests', function ($prQ) use ($editPullRequestId) {
                        $prQ->where('id', $editPullRequestId);
                    })
                        ->where('status', DocumentCategoryStatus::PUSHED->value);
                });
            })
            ->when($userBranchId, function ($query, $userBranchId) {
                $appliedFixRequestCategoryIds = FixRequest::where('status', FixRequestStatus::APPLIED->value)
                    ->whereNotNull('document_category_id')
                    ->whereHas('categoryVersion', function ($q) use ($userBranchId) {
                        $q->where('user_branch_id', $userBranchId);
                    })
                    ->pluck('document_category_id')
                    ->toArray();

                if (! empty($appliedFixRequestCategoryIds)) {
                    $query->orWhereIn('id', $appliedFixRequestCategoryIds);
                }
            });

        return $query->orderBy('position', 'asc')->get();
    }

    /**
     * ドキュメントバージョンとのリレーション
     */
    public function documentVersions()
    {
        return $this->hasMany(DocumentVersion::class, 'category_id');
    }

    /**
     * 親カテゴリとのリレーション
     */
    public function parent()
    {
        return $this->belongsTo(CategoryVersion::class, 'parent_entity_id', 'entity_id');
    }

    /**
     * 子カテゴリとのリレーション
     */
    public function children()
    {
        return $this->hasMany(CategoryVersion::class, 'parent_entity_id');
    }

    /**
     * ユーザーブランチとのリレーション
     */
    public function userBranch()
    {
        return $this->belongsTo(UserBranch::class, 'user_branch_id');
    }

    /**
     * 組織とのリレーション
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * カテゴリエンティティとのリレーション
     */
    public function entity()
    {
        return $this->belongsTo(CategoryEntity::class, 'entity_id');
    }

    /**
     * プルリクエスト編集セッションとのリレーション
     */
    public function pullRequestEditSession()
    {
        return $this->belongsTo(PullRequestEditSession::class, 'pull_request_edit_session_id');
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
     * パンクズリストを生成（親カテゴリを再帰的に取得）
     *
     * @return array カテゴリの階層配列（ルートから現在まで）
     */
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = [];
        $current = $this;

        // 親カテゴリを再帰的に辿って配列に追加
        while ($current) {
            array_unshift($breadcrumbs, [
                'id' => $current->id,
                'title' => $current->title,
            ]);
            $current = $current->parent;
        }

        return $breadcrumbs;
    }
}
