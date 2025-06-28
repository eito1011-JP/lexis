<?php

namespace App\Models;

use App\Constants\DocumentCategoryConstants;
use App\Traits\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentCategory extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'slug',
        'sidebar_label',
        'position',
        'description',
        'status',
        'parent_id',
        'user_branch_id',
        'is_deleted',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'position' => 'integer',
        'is_deleted' => 'boolean',
    ];

    /**
     * カテゴリパスからparentとなるcategory idを再帰的に取得
     *
     * @param  array  $categoryPath
     *                               parent/child/grandchildのカテゴリパスの場合, リクエストとして期待するのは['parent', 'child', 'grandchild']のような配列
     */
    public static function getIdFromPath(array $categoryPath): ?int
    {
        if (empty($categoryPath)) {
            return DocumentCategoryConstants::DEFAULT_CATEGORY_ID;
        }

        // デフォルトカテゴリ（uncategorized）から開始
        $parentId = DocumentCategoryConstants::DEFAULT_CATEGORY_ID;
        $currentParentCategoryId = null;

        foreach ($categoryPath as $slug) {
            $category = self::where('slug', $slug)
                ->where('parent_id', $parentId)
                ->first();

            if (! $category) {
                return DocumentCategoryConstants::DEFAULT_CATEGORY_ID;
            }

            $currentParentCategoryId = $category->id;
        }

        return $currentParentCategoryId;
    }

    /**
     * サブカテゴリを取得（ブランチ別）
     */
    public static function getSubCategories(int $parentId, ?int $userBranchId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = self::select('slug', 'sidebar_label', 'position')
            ->where('parent_id', $parentId);

        if ($userBranchId) {
            $query->where('user_branch_id', $userBranchId);
        }

        return $query->orderBy('position', 'asc')->get();
    }

    /**
     * ドキュメントとのリレーション
     */
    public function documents()
    {
        return $this->hasMany(Document::class, 'category_id');
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
        return $this->belongsTo(DocumentCategory::class, 'parent_id');
    }

    /**
     * 子カテゴリとのリレーション
     */
    public function children()
    {
        return $this->hasMany(DocumentCategory::class, 'parent_id');
    }

    /**
     * ユーザーブランチとのリレーション
     */
    public function userBranch()
    {
        return $this->belongsTo(UserBranch::class, 'user_branch_id');
    }
}
