<?php

namespace App\Models;

use App\Traits\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentVersionEntity extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'document_version_entities';

    protected $fillable = [
        'organization_id',
        'is_deleted',
        'deleted_at',
    ];

    protected $casts = [
        'organization_id' => 'integer',
        'is_deleted' => 'boolean',
    ];

    /**
     * 組織とのリレーション
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * ドキュメントバージョンとのリレーション
     */
    public function documentVersions()
    {
        return $this->hasMany(DocumentVersion::class, 'entity_id');
    }
}
