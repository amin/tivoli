<?php

namespace Database\Seeders;

use App\Models\AnimalType;
use App\Models\MetalType;
use App\Models\Stamptype;
use Illuminate\Database\Seeder;

class StamptypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (AnimalType::cases() as $animal) {
            Stamptype::create(['animal' => $animal, 'metal' => null]);

            foreach (MetalType::cases() as $metal) {
                Stamptype::create([
                    'animal' => $animal,
                    'metal' => $metal,
                ]);
            }
        }
    }
}
