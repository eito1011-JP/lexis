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
        'creator_id',
        'branch_name',
        'organization_id',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
    ];

    /**
     * ユーザーブランチセッションとのリレーション
     */
    public function userBranchSessions()
    {
        return $this->hasMany(UserBranchSession::class);
    }

    /**
     * 作成日時の降順でソートするスコープ
     */
    public function scopeOrderByCreatedAtDesc($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * ユーザーとのリレーション（作成者）
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
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
