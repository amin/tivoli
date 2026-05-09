<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IdentityToken extends Model
{
    protected $fillable = ['token', 'user_id', 'expires_at', 'consumed_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isValid(): bool
    {
        return $this->consumed_at === null && $this->expires_at->isFuture();
    }

    public static function issueFor(User $user, int $ttlMinutes = 5): self
    {
        return self::create([
            'token' => (string) Str::uuid(),
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);
    }
}
