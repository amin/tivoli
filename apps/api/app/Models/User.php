<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'name',          // set in seedern
    'access_key',    // set by backend when activated
])]
#[Hidden(['access_key'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'balance' => 'float',
        ];
    }

    // A user belongs to one group
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    // A user can do several transacations
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    // A user can have several stamps
    public function stamps(): HasMany
    {
        return $this->hasMany(Stamp::class);
    }

    // A vote belongs to one user
    public function vote(): HasOne
    {
        return $this->hasOne(Vote::class);
    }

}
