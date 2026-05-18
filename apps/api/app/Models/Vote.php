<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vote extends Model
{
    // These may be altered by users through form/API-requests
    protected $fillable = [
        'user_id',
        'amusement_id'
    ];

    // One vote comes from one user
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Each vote belongs to one amusement
    public function amusement(): BelongsTo
    {
        return $this->belongsTo(Amusement::class);
    }
}
