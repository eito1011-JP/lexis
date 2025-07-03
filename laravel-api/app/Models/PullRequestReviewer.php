<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PullRequestReviewer extends Model
{
    use HasFactory;

    protected $fillable = [
        'pull_request_id',
        'user_id',
    ];

    protected $casts = [
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
