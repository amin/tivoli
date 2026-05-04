<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

enum AnimalType: string
{
    case Lion = 'lion';
    case Dolphin = 'dolphin';
    case Toucan = 'toucan';
    case Beetlebug = 'beetlebug';
    case Snake = 'snake';
}

enum MetalType: string
{
    case Silver = 'silver';
    case Gold = 'gold';
    case Platinum = 'platinum';
}

class Stamp extends Model
{
    protected $fillable = ['user_id', 'animal', 'metal'];

    protected $appends = ['image_url'];

    protected $casts = [
        'animal' => AnimalType::class,
        'metal' => MetalType::class,
    ];

    protected function imageUrl(): Attribute
    {
        return Attribute::get(function (): string {
            $filename = $this->metal
                ? "{$this->metal->value}-{$this->animal->value}.svg"
                : "{$this->animal->value}.svg";

            return asset("images/stamps/{$filename}");
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function generate(int $userId): self
    {
        $animals = AnimalType::cases();
        $metals = MetalType::cases();

        $animal = $animals[array_rand($animals)];
        $metal = random_int(0, 1) === 1 ? $metals[array_rand($metals)] : null;

        return self::create([
            'user_id' => $userId,
            'animal' => $animal->value,
            'metal' => $metal?->value,
        ]);
    }
}
