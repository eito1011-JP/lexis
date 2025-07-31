<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PullRequestEditSessionDiff extends Model
{
    use HasFactory;

    protected $table = 'pull_request_edit_session_diffs';

    protected $fillable = [
        'pull_request_edit_session_id',
        'target_type',
        'diff_type',
        'original_version_id',
        'current_version_id',
    ];

    protected $casts = [
        'original_version_id' => 'integer',
        'current_version_id' => 'integer',
    ];

    /**
     * プルリクエスト編集セッションとのリレーション
     */
    public function pullRequestEditSession()
    {
        return $this->belongsTo(PullRequestEditSession::class, 'pull_request_edit_session_id');
    }

    /**
     * 元のドキュメントバージョンとのリレーション
     */
    public function originalDocumentVersion()
    {
        return $this->belongsTo(DocumentVersion::class, 'original_version_id');
    }

    /**
     * 現在のドキュメントバージョンとのリレーション
     */
    public function currentDocumentVersion()
    {
        return $this->belongsTo(DocumentVersion::class, 'current_version_id');
    }

    /**
     * 元のカテゴリバージョンとのリレーション
     */
    public function originalCategoryVersion()
    {
        return $this->belongsTo(DocumentCategory::class, 'original_version_id');
    }

    /**
     * 現在のカテゴリバージョンとのリレーション
     */
    public function currentCategoryVersion()
    {
        return $this->belongsTo(DocumentCategory::class, 'current_version_id');
    }
}
