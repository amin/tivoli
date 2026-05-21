<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'amusement_id',
        'stamp_id',
        'amount',
        'type',
        'settled_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'settled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function amusement(): BelongsTo
    {
        return $this->belongsTo(Amusement::class);
    }

    public function stamp(): BelongsTo
    {
        return $this->belongsTo(Stamp::class);
    }
}
