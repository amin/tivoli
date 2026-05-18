<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stamp extends Model
{
    protected $fillable = ['user_id', 'stamptype_id', 'source_amusement_id', 'exchanged_at'];

    protected $casts = [
        'exchanged_at' => 'datetime',
    ];

    protected $appends = ['animal', 'metal', 'image_url'];

    protected $hidden = ['stamptype', 'stamptype_id', 'user_id', 'exchanged_at', 'updated_at'];

    protected $with = ['stamptype'];

    protected function animal(): Attribute
    {
        return Attribute::get(fn() => $this->stamptype->animal->value);
    }

    protected function metal(): Attribute
    {
        return Attribute::get(fn() => $this->stamptype->metal?->value);
    }

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

    public function sourceAmusement(): BelongsTo
    {
        return $this->belongsTo(Amusement::class, 'source_amusement_id');
    }

    public static function generate(int $userId, int $amusementId): self
    {
        $animals = AnimalType::cases();
        $metals = MetalType::cases();

        $animal = $animals[array_rand($animals)];
        $metal = random_int(0, 1) === 1 ? $metals[array_rand($metals)] : null;

        $stamptype = Stamptype::where('animal', $animal->value)
            ->where('metal', $metal?->value)
            ->first();

        return self::create([
            'user_id' => $userId,
            'stamptype_id' => $stamptype->id,
            'source_amusement_id' => $amusementId,
        ]);
    }
}
