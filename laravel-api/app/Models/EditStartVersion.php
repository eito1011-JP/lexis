<?php

namespace App\Models;

use App\Traits\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EditStartVersion extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'edit_start_versions';

    protected $fillable = [
        'user_branch_id',
        'target_type',
        'original_version_id',
        'current_version_id',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
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
     * オリジナルカテゴリとのリレーション
     */
    public function originalCategory()
    {
        return $this->belongsTo(DocumentCategory::class, 'original_version_id');
    }

    /**
     * 現在のカテゴリとのリレーション
     */
    public function currentCategory()
    {
        return $this->belongsTo(DocumentCategory::class, 'current_version_id');
    }

    /**
     * target_typeに基づいてオリジナルオブジェクトを動的に取得（クエリビルダー使用）
     */
    public function getOriginalObject()
    {
        if ($this->target_type === 'document') {
            return DocumentVersion::withTrashed()->find($this->original_version_id);
        } elseif ($this->target_type === 'category') {
            return DocumentCategory::withTrashed()->find($this->original_version_id);
        }

        return null;
    }

    /**
     * target_typeに基づいて現在のオブジェクトを動的に取得（クエリビルダー使用）
     */
    public function getCurrentObject()
    {
        if ($this->target_type === 'document') {
            return DocumentVersion::withTrashed()->find($this->current_version_id);
        } elseif ($this->target_type === 'category') {
            return DocumentCategory::withTrashed()->find($this->current_version_id);
        }

        return null;
    }
}
