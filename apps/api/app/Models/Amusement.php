<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Amusement extends Model
{
    // These may be altered by users through form/API-requests
    protected $fillable = [
        'name',
        'description',
        'price',
        'player_payout',
        'url',
        'image_url',
        'type',
    ];

    protected $hidden = ['api_key'];

    protected $casts = [
        'price' => 'float',
        'player_payout' => 'float',
        'amusement_balance' => 'float',
        'buffer_required' => 'float',
        'buffer_locked' => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $amusement) {
            if (!$amusement->uuid) {
                $amusement->uuid = (string) Str::uuid();
            }
        });
    }

    // An amusement belongs to one group
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    // An amusement can have several transactions
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    // An amusement may have several votes
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
