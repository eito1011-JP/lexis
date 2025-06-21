<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'sidebar_label',
        'position',
        'description',
        'parent_id',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function documents()
    {
        return $this->hasMany(Document::class, 'category_id');
    }

    public function parent()
    {
        return $this->belongsTo(DocumentCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(DocumentCategory::class, 'parent_id');
    }
} 