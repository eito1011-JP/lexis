<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PreUser extends Model
{
    use HasFactory;

    protected $table = 'pre_users';

    protected $fillable = [
        'email',
        'token',
        'expired_at',
        'password',
        'is_invalidated',
        'invalidated_at',
    ];

    protected $casts = [
        'expired_at' => 'datetime',
        'invalidated_at' => 'datetime',
        'is_invalidated' => 'boolean',
    ];

    public function scopeByEmail(Builder $query, string $email): Builder
    {
        return $query->where('email', $email);
    }

    public function scopeIsInvalidated(Builder $query, bool $isInvalidated): Builder
    {
        return $query->where('is_invalidated', $isInvalidated);
    }

    public function scopeByToken(Builder $query, string $token): Builder
    {
        return $query->where('token', $token);
    }

    public function scopeWhereExpiredAt(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('expired_at')->orWhere('expired_at', '>', now());
        });
    }
}


