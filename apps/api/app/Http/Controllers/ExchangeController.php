<?php

namespace App\Http\Controllers;

use App\Models\Stamp;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExchangeController extends Controller
{
    private const RATES = [
        'metal' => ['amount' => 10, 'count' => 3],
        'animal' => ['amount' => 7,  'count' => 5],
        'non_metal' => ['amount' => 3,  'count' => 3],
    ];

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'set_type' => ['required', 'string', 'in:metal,animal,non_metal'],
            'stamp_ids' => ['required', 'array'],
            'stamp_ids.*' => ['integer'],
        ]);

        $user = User::findOrFail($validated['user_id']);
        $setType = $validated['set_type'];
        $stampIds = array_unique($validated['stamp_ids']);
        $required = self::RATES[$setType]['count'];

        if (count($stampIds) !== $required) {
            return response()->json([
                'message' => "A {$setType} set requires exactly {$required} stamps.",
            ], 422);
        }

        $stamps = Stamp::whereIn('id', $stampIds)
            ->where('user_id', $user->id)
            ->whereNull('exchanged_at')
            ->with('stamptype')
            ->get();

        if ($stamps->count() !== $required) {
            return response()->json(['message' => 'One or more stamp IDs do not belong to you.'], 422);
        }

        $error = $this->validateSet($stamps, $setType);
        if ($error) {
            return response()->json(['message' => $error], 422);
        }

        $amount = self::RATES[$setType]['amount'];

        DB::transaction(function () use ($stampIds, $user, $amount) {
            Stamp::whereIn('id', $stampIds)->update(['exchanged_at' => now()]);
            $user->increment('balance', $amount);
        });

        return response()->json([
            'amount' => $amount,
            'set_type' => $setType,
            'stamps_consumed' => $required,
        ]);
    }

    private function validateSet($stamps, string $setType): ?string
    {
        return match ($setType) {
            'metal' => $this->validateMetalSet($stamps),
            'animal' => $this->validateAnimalSet($stamps),
            'non_metal' => $this->validateNonMetalSet($stamps),
        };
    }

    private function validateMetalSet($stamps): ?string
    {
        $metals = $stamps->map(fn($s) => $s->stamptype->metal?->value)->filter()->sort()->values()->all();
        $required = ['gold', 'platinum', 'silver'];

        if ($metals !== $required) {
            return 'A metal set requires one silver, one gold, and one platinum stamp.';
        }

        return null;
    }

    private function validateAnimalSet($stamps): ?string
    {
        $animals = $stamps->map(fn($s) => $s->stamptype->animal->value)->sort()->values()->all();
        $required = ['beetlebug', 'dolphin', 'lion', 'snake', 'toucan'];

        if ($animals !== $required) {
            return 'An animal set requires one of each animal: lion, dolphin, toucan, beetlebug, snake.';
        }

        return null;
    }

    private function validateNonMetalSet($stamps): ?string
    {
        $hasMetals = $stamps->filter(fn($s) => $s->stamptype->metal !== null);
        if ($hasMetals->isNotEmpty()) {
            return 'A non-metal set may only contain stamps with no metal.';
        }

        $animals = $stamps->map(fn($s) => $s->stamptype->animal->value)->unique();
        if ($animals->count() !== 3) {
            return 'A non-metal set requires 3 stamps from 3 different animals.';
        }

        return null;
    }
}
