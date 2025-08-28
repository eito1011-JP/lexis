<?php

namespace App\Models;

use App\Traits\HasOrganizationScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PullRequest extends Model
{
    use HasFactory;
    use HasOrganizationScope;

    protected $fillable = [
        'user_branch_id',
        'title',
        'description',
        'github_url',
        'pr_number',
        'status',
        'organization_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * ユーザーブランチとのリレーション
     */
    public function userBranch()
    {
        return $this->belongsTo(UserBranch::class);
    }

    /**
     * 組織とのリレーション
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * レビュアーとのリレーション
     */
    public function reviewers()
    {
        return $this->hasMany(PullRequestReviewer::class);
    }

    /**
     * レビュアーのユーザーとのリレーション
     */
    public function reviewerUsers()
    {
        return $this->belongsToMany(User::class, 'pull_request_reviewers');
    }

    /**
     * コメントとのリレーション
     */
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * 修正リクエスト（FixRequest）とのリレーション
     */
    public function fixRequests()
    {
        return $this->hasMany(FixRequest::class);
    }

    /**
     * プルリクエスト編集セッションとのリレーション
     */
    public function pullRequestEditSessions()
    {
        return $this->hasMany(PullRequestEditSession::class);
    }
}
