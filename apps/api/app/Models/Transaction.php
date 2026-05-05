<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    // These may be altered by users through form/API-requests
    protected $fillable = [
        'user_id',
        'amusement_id',
        'amount',
        'type'
    ];

    // A transaction belongs to one user
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // A transaction belongs to one amusement
    public function amusement(): BelongsTo
    {
        return $this->belongsTo(Amusement::class);
    }
}
