<?php

namespace App\Models;

use App\Models\CategoryVersion;
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
        'status',
        'organization_id',
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
    public function categoryVersion()
    {
        return $this->belongsTo(CategoryVersion::class, 'document_category_id');
    }

    /**
     * ベースとなるドキュメントカテゴリとのリレーション
     */
    public function baseCategoryVersion()
    {
        return $this->belongsTo(CategoryVersion::class, 'base_category_version_id');
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
     * 組織とのリレーション
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * アクティビティログとのリレーション
     */
    public function activityLogs()
    {
        return $this->hasMany(ActivityLogOnPullRequest::class);
    }
}
