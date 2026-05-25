<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stamptype extends Model
{
    protected $fillable = [
        'animal',
        'metal',
    ];

    protected $appends = ['image_url'];

    protected $casts = [
        'animal' => AnimalType::class,
        'metal'  => MetalType::class,
    ];

    protected function imageUrl(): Attribute
    {
        return Attribute::get(function (): string {
            $filename = $this->metal
                ? "{$this->metal->value}-{$this->animal->value}.svg"
                : "{$this->animal->value}.svg";

            return asset("images/stamps/transparent/{$filename}");
        });
    }

    public function stamps(): HasMany
    {
        return $this->hasMany(Stamp::class);
    }
}
