<?php

namespace App\Models;

use App\Constants\DocumentCategoryConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'sidebar_label',
        'position',
        'description',
        'parent_id',
        'user_branch_id',
        'is_deleted',
    ];

    protected $casts = [
        'position' => 'integer',
        'is_deleted' => 'boolean',
    ];

    /**
     * カテゴリパスからカテゴリIDを取得
     */
    public static function getIdFromPath(array $categoryPath): ?int
    {
        if (empty($categoryPath)) {
            // デフォルトカテゴリを取得
            $defaultCategory = self::where('slug', DocumentCategoryConstants::DEFAULT_CATEGORY_SLUG)->first();

            return $defaultCategory ? $defaultCategory->id : null;
        }

        $parentId = null;
        $currentCategoryId = null;

        foreach ($categoryPath as $slug) {
            $category = self::where('slug', $slug)
                ->where('parent_id', $parentId)
                ->first();

            if (! $category) {
                return null;
            }

            $currentCategoryId = $category->id;
            $parentId = $category->id;
        }

        return $currentCategoryId;
    }

    /**
     * サブカテゴリを取得（ブランチ別）
     */
    public static function getSubCategories(int $parentId, ?int $userBranchId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = self::select('slug', 'sidebar_label', 'position')
            ->where('parent_id', $parentId)
            ->where('is_deleted', 0);

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
