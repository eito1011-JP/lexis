<?php

namespace App\Models;

use App\Traits\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'sessions';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'user_id',
        'sess',
        'expired_at',
    ];

    protected $casts = [
        'expired_at' => 'datetime',
    ];

    /**
     * セッションからユーザー情報を取得
     */
    public static function getUserFromSession(string $sessionId): ?User
    {
        $session = self::where('id', $sessionId)
            ->where('expired_at', '>', now())
            ->first();

        if (! $session) {
            return null;
        }

        return $session->user;
    }

    /**
     * ユーザーとのリレーション
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
