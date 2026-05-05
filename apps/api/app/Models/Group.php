<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    // One group has several users
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // One group can own several amusements
    public function amusements(): HasMany
    {
        return $this->hasMany(Amusement::class);
    }
}
