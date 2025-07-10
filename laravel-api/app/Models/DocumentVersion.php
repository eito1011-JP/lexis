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
    public static function getDocumentsByCategoryId(int $categoryId, ?int $userBranchId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = self::select('sidebar_label', 'slug', 'is_public', 'status', 'last_edited_by', 'file_order')
            ->where('category_id', $categoryId)
            ->where(function ($q) use ($userBranchId) {
                $q->where('status', DocumentStatus::MERGED->value)
                    ->orWhere(function ($subQ) use ($userBranchId) {
                        $subQ->where('user_branch_id', $userBranchId)
                            ->where('status', DocumentStatus::DRAFT->value);
                    });
            });

        return $query->get();
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
