<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLogOnPullRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'pull_request_id',
        'comment_id',
        'reviewer_id',
        'pull_request_edit_session_id',
        'action',
        'old_pull_request_title',
        'new_pull_request_title',
        'fix_request_token',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * ユーザーとのリレーション
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * プルリクエストとのリレーション
     */
    public function pullRequest()
    {
        return $this->belongsTo(PullRequest::class);
    }

    /**
     * コメントとのリレーション
     */
    public function comment()
    {
        return $this->belongsTo(Comment::class);
    }

    /**
     * 修正リクエストとのリレーション
     */
    public function fixRequest()
    {
        return $this->belongsTo(FixRequest::class);
    }

    /**
     * レビュアーとのリレーション
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * プルリクエスト編集セッションとのリレーション
     */
    public function pullRequestEditSession()
    {
        return $this->belongsTo(PullRequestEditSession::class, 'pull_request_edit_session_id');
    }
}
