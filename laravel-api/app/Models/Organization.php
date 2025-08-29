<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
    ];

    /**
     * ユーザーとのリレーション（多対多）
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'organization_members')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    /**
     * 組織メンバーとのリレーション
     */
    public function members()
    {
        return $this->hasMany(OrganizationMember::class);
    }

    /**
     * 組織ロールバインディングとのリレーション
     */
    public function roleBindings()
    {
        return $this->hasMany(OrganizationRoleBinding::class);
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
     * プルリクエストとのリレーション
     */
    public function pullRequests()
    {
        return $this->hasMany(PullRequest::class);
    }

    /**
     * ユーザーブランチとのリレーション
     */
    public function userBranches()
    {
        return $this->hasMany(UserBranch::class);
    }
}
