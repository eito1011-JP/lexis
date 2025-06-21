<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserBranch extends Model
{
    use HasFactory;

    protected $table = 'user_branches';

    protected $fillable = [
        'user_id',
        'branch_name',
        'snapshot_commit',
        'is_active',
        'pr_status',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * ユーザーのアクティブなブランチを取得
     */
    public static function getActiveBranch(int $userId): ?self
    {
        return self::where('user_id', $userId)
            ->where('is_active', 1)
            ->where('pr_status', 'none')
            ->first();
    }

    /**
     * ユーザーとのリレーション
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * ドキュメントバージョンとのリレーション
     */
    public function documentVersions()
    {
        return $this->hasMany(DocumentVersion::class);
    }

    /**
     * ドキュメントカテゴリとのリレーション
     */
    public function documentCategories()
    {
        return $this->hasMany(DocumentCategory::class);
    }

    /**
     * 編集開始バージョンとのリレーション
     */
    public function editStartVersions()
    {
        return $this->hasMany(EditStartVersion::class);
    }
}
