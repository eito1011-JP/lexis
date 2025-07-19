<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FixRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_version_id',
        'document_category_id',
        'user_id',
        'pull_request_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * ドキュメントバージョンとのリレーション
     */
    public function documentVersion()
    {
        return $this->belongsTo(DocumentVersion::class);
    }

    /**
     * ドキュメントカテゴリとのリレーション
     */
    public function documentCategory()
    {
        return $this->belongsTo(DocumentCategory::class);
    }

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
     * アクティビティログとのリレーション
     */
    public function activityLogs()
    {
        return $this->hasMany(ActivityLogOnPullRequest::class);
    }
}
