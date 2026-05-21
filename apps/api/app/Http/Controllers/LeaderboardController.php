<?php

namespace App\Http\Controllers;

use App\Models\Amusement;
use App\Models\User;
use App\Services\VpCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
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
                'group.amusements',
            ])
            ->get()
            ->map(fn($u) => [
                'name'     => $u->name,
                'group'    => $u->group?->name,
                'total_vp' => $u->hasNegativeGroupAmusement()
                    ? 0
                    : VpCalculator::compute($u->stamps)['total'],
            ])
            ->sortByDesc('total_vp')
            ->values();

        $voteWinners = Amusement::withCount('votes')
            ->with('group')
            ->orderByDesc('votes_count')
            ->get()
            ->map(fn($a) => [
                'name' => $a->name,
                'group' => $a->group?->name,
                'votes' => $a->votes_count,
            ])
            ->values();

        return response()->json([
            'money_leaders' => $moneyLeaders,
            'vp_leaders' => $vpLeaders,
            'vote_winners' => $voteWinners,
        ]);
    }
}
