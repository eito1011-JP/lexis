<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;

    use Notifiable;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'nickname',
        'last_login',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * ユーザーブランチセッションとのリレーション
     */
    public function userBranchSessions()
    {
        return $this->hasMany(UserBranchSession::class);
    }

    /**
     * ユーザーブランチとのリレーション（作成者として）
     */
    public function createdUserBranches()
    {
        return $this->hasMany(UserBranch::class, 'creator_id');
    }

    /**
     * ユーザーブランチとのリレーション（セッションを通じて）
     */
    public function userBranches()
    {
        return $this->belongsToMany(UserBranch::class, 'user_branch_sessions');
    }

    /**
     * レビュアーとして参加しているプルリクエストとのリレーション
     */
    public function reviewingPullRequests()
    {
        return $this->belongsToMany(PullRequest::class, 'pull_request_reviewers');
    }

    /**
     * プルリクエストレビュアーとのリレーション
     */
    public function pullRequestReviewers()
    {
        return $this->hasMany(PullRequestReviewer::class);
    }

    /**
     * コメントとのリレーション
     */
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function scopeByEmail(Builder $query, string $email): Builder
    {
        return $query->where('email', $email);
    }

    public function organizationMember()
    {
        return $this->hasOne(OrganizationMember::class);
    }
}
