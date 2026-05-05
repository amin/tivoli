<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stamp extends Model
{
    protected $fillable = ['user_id', 'stamptype_id', 'exchanged_at'];

    protected $casts = [
        'exchanged_at' => 'datetime',
    ];

    protected $appends = ['image_url'];

    protected $with = ['stamptype'];

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn() => $this->stamptype->image_url);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stamptype(): BelongsTo
    {
        return $this->belongsTo(Stamptype::class);
    }

    public static function generate(int $userId): self
    {
        $animals = AnimalType::cases();
        $metals = MetalType::cases();

        $animal = $animals[array_rand($animals)];
        $metal = random_int(0, 1) === 1 ? $metals[array_rand($metals)] : null;

        $stamptype = Stamptype::where('animal', $animal->value)
            ->where('metal', $metal?->value)
            ->first();

        return self::create([
            'user_id'      => $userId,
            'stamptype_id' => $stamptype->id,
        ]);
    }
}
