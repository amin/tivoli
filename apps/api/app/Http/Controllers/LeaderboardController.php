<?php

namespace App\Http\Controllers;

use App\Models\Amusement;
use App\Models\AnimalType;
use App\Models\User;
use App\Services\VpCalculator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    private const METAL_SET_VP  = 40;
    private const ANIMAL_SET_VP = 25;

    public function show(Request $request): JsonResponse
    {
        $moneyLeaders = User::with('group')
            ->orderByDesc('balance')
            ->get()
            ->map(fn($u) => [
                'name' => $u->name,
                'group' => $u->group?->name,
                'balance' => round($u->balance, 2),
            ])
            ->values();

        $vpLeaders = User::with([
                'stamps' => fn($q) => $q->whereNull('exchanged_at'),
                'group',
            ])
            ->get()
            ->map(fn($u) => [
                'name'     => $u->name,
                'group'    => $u->group?->name,
                'total_vp' => $this->computeVP($u->stamps),
                'name' => $u->name,
                'group' => $u->group?->name,
                'total_vp' => VpCalculator::compute($u->stamps)['total'],
            ])
            ->sortByDesc('total_vp')
            ->values();

        $voteWinners = Amusement::withCount('votes')
            ->with('group')
            ->orderByDesc('votes_count')
            ->get()
            ->map(fn($a) => [
                'name'  => $a->name,
                'group' => $a->group?->name,
                'votes' => $a->votes_count,
            ])
            ->values();

        return response()->json([
            'money_leaders' => $moneyLeaders,
            'vp_leaders'    => $vpLeaders,
            'vote_winners'  => $voteWinners,
        ]);
    }

    private function computeVP(Collection $stamps): int
    {
        $silverCount   = 0;
        $goldCount     = 0;
        $platinumCount = 0;

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

        return $metalSetVP + $animalSetVP + $looseVP;
            'vp_leaders' => $vpLeaders,
            'vote_winners' => $voteWinners,
        ]);
    }
}
