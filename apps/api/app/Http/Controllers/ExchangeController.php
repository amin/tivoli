<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExchangeRequest;
use App\Models\Stamp;
use App\Models\User;
use App\Models\AnimalType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ExchangeController extends Controller
{
    private const RATES = [
        'metal' => ['amount' => 10, 'count' => 3],
        'animal' => ['amount' => 7,  'count' => 5],
        'non_metal' => ['amount' => 3,  'count' => 3],
    ];

    public function store(StoreExchangeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user      = $request->user();
        $stampIds  = array_unique($validated['stamp_ids']);

        $stamps = Stamp::whereIn('id', $stampIds)
            ->where('user_id', $user->id)
            ->whereNull('exchanged_at')
            ->with('stamptype')
            ->get();

        if ($stamps->count() !== count($stampIds)) {
            return response()->json(['message' => 'One or more stamp IDs do not belong to you.'], 422);
        }

        [$consumedIds, $metalSets, $animalSets, $nonMetalSets] = $this->formSets($stamps);

        $totalAmount   = ($metalSets * 10) + ($animalSets * 7) + ($nonMetalSets * 3);
        $stampsConsumed = count($consumedIds);

        if ($stampsConsumed > 0) {
            DB::transaction(function () use ($consumedIds, $user, $totalAmount) {
                Stamp::whereIn('id', $consumedIds)->update(['exchanged_at' => now()]);
                $user->increment('balance', $totalAmount);
            });
        }

        return response()->json([
            'amount'         => $totalAmount,
            'sets_exchanged' => [
                'metal_sets'     => $metalSets,
                'animal_sets'    => $animalSets,
                'non_metal_sets' => $nonMetalSets,
            ],
            'stamps_consumed' => $stampsConsumed,
        ]);
    }

    private function formSets($stamps): array
    {
        $consumedIds = [];

        // 1. Metal sets: one silver + one gold + one platinum
        $byMetal = [];
        foreach (['silver', 'gold', 'platinum'] as $metal) {
            $byMetal[$metal] = $stamps
                ->filter(fn($s) => $s->stamptype->metal?->value === $metal)
                ->pluck('id')
                ->toArray();
        }

        $metalSets = min(array_map('count', $byMetal));
        foreach ($byMetal as $ids) {
            array_push($consumedIds, ...array_slice($ids, 0, $metalSets));
        }

        // 2. Animal sets: one of each of the five animals, from remaining stamps
        $remaining = $stamps->whereNotIn('id', $consumedIds);
        $byAnimal  = [];
        foreach (AnimalType::cases() as $animal) {
            $byAnimal[$animal->value] = $remaining
                ->filter(fn($s) => $s->stamptype->animal->value === $animal->value)
                ->pluck('id')
                ->toArray();
        }

        $animalSets = min(array_map('count', $byAnimal));
        foreach ($byAnimal as $ids) {
            array_push($consumedIds, ...array_slice($ids, 0, $animalSets));
        }

        // 3. Non-metal sets: 3 distinct non-metal animals, from remaining stamps
        $remaining = $stamps->whereNotIn('id', $consumedIds);
        $nonMetalByAnimal = [];
        foreach (AnimalType::cases() as $animal) {
            $ids = $remaining
                ->filter(fn($s) => $s->stamptype->metal === null
                    && $s->stamptype->animal->value === $animal->value)
                ->pluck('id')
                ->toArray();
            if ($ids) {
                $nonMetalByAnimal[$animal->value] = $ids;
            }
        }

        $nonMetalSets = 0;
        while (count($nonMetalByAnimal) >= 3) {
            $animals = array_keys($nonMetalByAnimal);
            for ($i = 0; $i < 3; $i++) {
                $consumedIds[] = array_shift($nonMetalByAnimal[$animals[$i]]);
                if (empty($nonMetalByAnimal[$animals[$i]])) {
                    unset($nonMetalByAnimal[$animals[$i]]);
                }
            }
            $nonMetalSets++;
        }

        return [$consumedIds, $metalSets, $animalSets, $nonMetalSets];
    }
}
