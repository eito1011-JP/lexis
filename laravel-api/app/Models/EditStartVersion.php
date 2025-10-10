<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EditStartVersion extends Model
{
    use HasFactory;

    protected $table = 'edit_start_versions';

    protected $fillable = [
        'user_branch_id',
        'commit_id',
        'target_type',
        'entity_id',
        'original_version_id',
        'current_version_id',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'user_branch_id' => 'integer',
        'commit_id' => 'integer',
        'entity_id' => 'integer',
        'original_version_id' => 'integer',
        'current_version_id' => 'integer',
    ];

    /**
     * ユーザーブランチとのリレーション
     */
    public function userBranch()
    {
        return $this->belongsTo(UserBranch::class, 'user_branch_id');
    }

    /**
     * コミットとのリレーション
     */
    public function commit()
    {
        return $this->belongsTo(Commit::class, 'commit_id');
    }

    /**
     * オリジナルドキュメントバージョンとのリレーション
     */
    public function originalDocumentVersion()
    {
        return $this->belongsTo(DocumentVersion::class, 'original_version_id');
    }

    /**
     * 現在のドキュメントバージョンとのリレーション
     */
    public function currentDocumentVersion()
    {
        return $this->belongsTo(DocumentVersion::class, 'current_version_id');
    }

    /**
     * オリジナルカテゴリバージョンとのリレーション
     */
    public function originalCategoryVersion()
    {
        return $this->belongsTo(CategoryVersion::class, 'original_version_id');
    }

    /**
     * 現在のカテゴリバージョンとのリレーション
     */
    public function currentCategoryVersion()
    {
        return $this->belongsTo(CategoryVersion::class, 'current_version_id');
    }

    /**
     * target_typeに基づいて元のオブジェクトを動的に取得
     */
    public function getOriginalObject()
    {
        if (! $this->original_version_id) {
            return null;
        }

        switch ($this->target_type) {
            case 'document':
                return DocumentVersion::withTrashed()->find($this->original_version_id);
            case 'category':
                return CategoryVersion::withTrashed()->find($this->original_version_id);
            default:
                return null;
        }
    }

    /**
     * target_typeに基づいて現在のオブジェクトを動的に取得
     */
    public function getCurrentObject()
    {
        switch ($this->target_type) {
            case 'document':
                return DocumentVersion::withTrashed()->find($this->current_version_id);
            case 'category':
                return CategoryVersion::withTrashed()->find($this->current_version_id);
            default:
                return null;
        }
    }
}
