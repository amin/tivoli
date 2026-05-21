<?php

namespace App\Services;

use App\Models\AnimalType;
use Illuminate\Database\Eloquent\Collection;

class VpCalculator
{
    private const METAL_SET_VP = 40;
    private const ANIMAL_SET_VP = 25;

    public static function compute(Collection $stamps): array
    {
        $silverCount = 0;
        $goldCount = 0;
        $platinumCount = 0;

        $metalByAnimal = array_fill_keys(array_column(AnimalType::cases(), 'value'), 0);
        $nonMetalByAnimal = array_fill_keys(array_column(AnimalType::cases(), 'value'), 0);

        foreach ($stamps as $stamp) {
            $animal = $stamp->stamptype->animal->value;
            $metal = $stamp->stamptype->metal?->value;

            if ($metal === null) {
                $nonMetalByAnimal[$animal]++;
            } else {
                $metalByAnimal[$animal]++;
                match ($metal) {
                    'silver' => $silverCount++,
                    'gold' => $goldCount++,
                    'platinum' => $platinumCount++,
                };
            }
        }

        $animalCounts = array_map(
            fn($a) => $metalByAnimal[$a] + $nonMetalByAnimal[$a],
            array_column(AnimalType::cases(), 'value')
        );

        $metalSets  = min($silverCount, $goldCount, $platinumCount);
        $metalSetVP = $metalSets * self::METAL_SET_VP;

        $animalSets  = min($animalCounts);
        $animalSetVP = $animalSets * self::ANIMAL_SET_VP;

        $totalMetal = $silverCount + $goldCount + $platinumCount;
        $metalConsumedByAnimalSets = 0;
        foreach (AnimalType::cases() as $animal) {
            $metalConsumedByAnimalSets += max(0, $animalSets - $nonMetalByAnimal[$animal->value]);
        }

        $looseMetal = $totalMetal - $metalConsumedByAnimalSets;
        $looseVP    = intdiv($looseMetal * ($looseMetal + 1), 2);

        return [
            'total'       => $metalSetVP + $animalSetVP + $looseVP,
            'metal_sets'  => ['count' => $metalSets,  'vp' => $metalSetVP],
            'animal_sets' => ['count' => $animalSets, 'vp' => $animalSetVP],
            'loose_metal' => ['count' => $looseMetal, 'vp' => $looseVP],
        ];
    }
}
