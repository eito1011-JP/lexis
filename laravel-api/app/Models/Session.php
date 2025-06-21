<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    use HasFactory;

    protected $table = 'sessions';

    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'user_id',
        'email',
        'expire_at',
    ];

    protected $casts = [
        'expire_at' => 'datetime',
    ];

    /**
     * ユーザーとのリレーション
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
} 