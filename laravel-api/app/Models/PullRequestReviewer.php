<?php

namespace App\Models;

use App\Enums\PullRequestReviewerActionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PullRequestReviewer extends Model
{
    use HasFactory;

    protected $fillable = [
        'pull_request_id',
        'user_id',
        'action_status',
    ];

    protected $casts = [
        'action_status' => PullRequestReviewerActionStatus::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * プルリクエストとのリレーション
     */
    public function pullRequest()
    {
        return $this->belongsTo(PullRequest::class);
    }

    /**
     * ユーザーとのリレーション
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
