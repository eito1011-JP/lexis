<?php

namespace App\Models;

use App\Consts\Flag;
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
        'organization_id',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * アクティブなブランチのスコープ
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', Flag::TRUE);
    }

    /**
     * 作成日時の降順でソートするスコープ
     */
    public function scopeOrderByCreatedAtDesc($query)
    {
        return $query->orderBy('created_at', 'desc');
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
     * ドキュメントバージョンとのリレーション
     */
    public function documentVersions()
    {
        return $this->hasMany(DocumentVersion::class);
    }

    /**
     * ドキュメントカテゴリとのリレーション
     */
    public function categoryVersions()
    {
        return $this->hasMany(CategoryVersion::class);
    }

    /**
     * 編集開始バージョンとのリレーション
     */
    public function editStartVersions()
    {
        return $this->hasMany(EditStartVersion::class);
    }

    /**
     * プルリクエストとのリレーション
     */
    public function pullRequests()
    {
        return $this->hasMany(PullRequest::class);
    }
}
