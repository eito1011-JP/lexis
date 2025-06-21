<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'sidebar_label',
        'slug',
        'is_public',
        'status',
        'last_edited_by',
        'file_order',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'file_order' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(DocumentCategory::class, 'category_id');
    }
} 