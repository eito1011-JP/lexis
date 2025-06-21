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
}
