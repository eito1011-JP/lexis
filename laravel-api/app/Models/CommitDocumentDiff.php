<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommitDocumentDiff extends Model
{
    use HasFactory;

    protected $table = 'commit_document_diffs';

    protected $fillable = [
        'commit_id',
        'document_entity_id',
        'change_type',
        'is_title_changed',
        'is_description_changed',
        'first_original_version_id',
        'last_current_version_id',
    ];

    protected $casts = [
        'commit_id' => 'integer',
        'document_entity_id' => 'integer',
        'is_title_changed' => 'boolean',
        'is_description_changed' => 'boolean',
        'first_original_version_id' => 'integer',
        'last_current_version_id' => 'integer',
    ];

    /**
     * コミットとのリレーション
     */
    public function commit()
    {
        return $this->belongsTo(Commit::class, 'commit_id');
    }

    /**
     * ドキュメントエンティティとのリレーション
     */
    public function documentEntity()
    {
        return $this->belongsTo(DocumentEntity::class, 'document_entity_id');
    }

    /**
     * 最初のオリジナルバージョンとのリレーション
     */
    public function firstOriginalVersion()
    {
        return $this->belongsTo(DocumentVersion::class, 'first_original_version_id');
    }

    /**
     * 最後のカレントバージョンとのリレーション
     */
    public function lastCurrentVersion()
    {
        return $this->belongsTo(DocumentVersion::class, 'last_current_version_id');
    }
}
