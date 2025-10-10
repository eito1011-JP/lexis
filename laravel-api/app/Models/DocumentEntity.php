<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentEntity extends Model
{
    use HasFactory;

    protected $table = 'document_entities';

    protected $fillable = [
        'organization_id',
    ];

    protected $casts = [
        'organization_id' => 'integer',
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
