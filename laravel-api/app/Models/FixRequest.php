<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FixRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'document_version_id',
        'document_category_id',
        'base_document_version_id',
        'base_category_version_id',
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
     * ベースとなるドキュメントバージョンとのリレーション
     */
    public function baseDocumentVersion()
    {
        return $this->belongsTo(DocumentVersion::class, 'base_document_version_id');
    }

    /**
     * ドキュメントカテゴリとのリレーション
     */
    public function documentCategory()
    {
        return $this->belongsTo(DocumentCategory::class);
    }

    /**
     * ベースとなるドキュメントカテゴリとのリレーション
     */
    public function baseCategory()
    {
        return $this->belongsTo(DocumentCategory::class, 'base_category_version_id');
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
