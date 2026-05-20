<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IdentityToken extends Model
{
    public const DEFAULT_TTL_MINUTES = 30;

    protected $fillable = ['token', 'user_id', 'expires_at', 'consumed_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Multi-use during TTL. `consumed_at` tracks the first time the token was
    // used (its stamp-issuing chance is spent at that moment), but the token
    // remains valid for additional transactions until it expires.
    public function isValid(): bool
    {
        return $this->expires_at->isFuture();
    }

    public static function issueFor(User $user, int $ttlMinutes = self::DEFAULT_TTL_MINUTES): self
    {
        return self::create([
            'token' => (string) Str::uuid(),
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);
    }
}
