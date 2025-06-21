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
    public static function getUserFromSession(string $sessionId): ?array
    {
        $session = self::where('id', $sessionId)
            ->where('expired_at', '>', now())
            ->first();

        if (! $session) {
            return null;
        }

        $sessionData = json_decode($session->sess, true);

        return [
            'userId' => $sessionData['userId'],
            'email' => $sessionData['email'],
        ];
    }

    /**
     * ユーザーとのリレーション
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
