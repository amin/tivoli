<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stamptype extends Model
{
    // These may be altered by users through form/API-requests
    protected $fillable = [
        'animal',
        'metal',
    ];
    // One stamptype has several stamps
    public function stamps(): HasMany
    {
        return $this->hasMany(Stamp::class);
    }
}
