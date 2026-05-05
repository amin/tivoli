<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stamp extends Model
{
    // These may be altered by users through form/API-requests
    protected $fillable = [
        'user_id',
        'stamptype_id',
    ];

    // A stamp belongs to a stamptype
    public function stamptype(): BelongsTo
    {
        return $this->belongsTo(Stamptype::class);
    }

    // A user can have several stamps
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
