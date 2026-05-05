<?php

namespace App\Http\Controllers;

use App\Models\AnimalType;
use App\Models\MetalType;
use App\Models\Stamp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VictoryPointsController extends Controller
{
    private const METAL_SET_VP  = 40;
    private const ANIMAL_SET_VP = 25;

    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $stamps = Stamp::where('user_id', $validated['user_id'])
            ->whereNull('exchanged_at')
            ->with('stamptype')
            ->get();

        $silverCount   = 0;
        $goldCount     = 0;
        $platinumCount = 0;

        // Track metal and non-metal counts per animal separately,
        // so we know how many metal stamps the animal set must consume.
        $metalByAnimal    = array_fill_keys(array_column(AnimalType::cases(), 'value'), 0);
        $nonMetalByAnimal = array_fill_keys(array_column(AnimalType::cases(), 'value'), 0);

        foreach ($stamps as $stamp) {
            $animal = $stamp->stamptype->animal->value;
            $metal  = $stamp->stamptype->metal?->value;

            if ($metal === null) {
                $nonMetalByAnimal[$animal]++;
            } else {
                $metalByAnimal[$animal]++;
                match ($metal) {
                    'silver'   => $silverCount++,
                    'gold'     => $goldCount++,
                    'platinum' => $platinumCount++,
                };
            }
        }

        $animalCounts = array_map(
            fn($a) => $metalByAnimal[$a] + $nonMetalByAnimal[$a],
            array_column(AnimalType::cases(), 'value')
        );

        // Metal sets: one silver + one gold + one platinum (any animals, no consumption)
        $metalSets  = min($silverCount, $goldCount, $platinumCount);
        $metalSetVP = $metalSets * self::METAL_SET_VP;

        // Animal sets: one of each of the 5 animals (any metal, no consumption)
        $animalSets  = min($animalCounts);
        $animalSetVP = $animalSets * self::ANIMAL_SET_VP;

        // Loose metal: all metal stamps minus those the animal set must consume.
        // The animal set prefers non-metal stamps; it only consumes a metal stamp
        // for an animal when there are not enough non-metal ones to cover the sets needed.
        $totalMetal = $silverCount + $goldCount + $platinumCount;
        $metalConsumedByAnimalSets = 0;
        foreach (AnimalType::cases() as $animal) {
            $metalConsumedByAnimalSets += max(0, $animalSets - $nonMetalByAnimal[$animal->value]);
        }

        $looseMetal = $totalMetal - $metalConsumedByAnimalSets;
        $looseVP    = intdiv($looseMetal * ($looseMetal + 1), 2);

        $totalVP = $metalSetVP + $animalSetVP + $looseVP;

        return response()->json([
            'total_vp'  => $totalVP,
            'breakdown' => [
                'metal_sets'  => ['count' => $metalSets,  'vp' => $metalSetVP],
                'animal_sets' => ['count' => $animalSets, 'vp' => $animalSetVP],
                'loose_metal' => ['count' => $looseMetal, 'vp' => $looseVP],
            ],
        ]);
    }
}
