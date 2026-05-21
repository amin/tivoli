<?php

namespace App\Http\Controllers;

use App\Models\Amusement;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettleController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user()->load('group');

        if (!$user->group?->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $details = [];

        DB::transaction(function () use (&$details) {
            $amusements = Amusement::whereNull('settled_at')->with('group.users')->get();
            foreach ($amusements as $amusement) {
                $members = $amusement->group?->users ?? collect();
                $memberCount = $members->count();

                // Reclaim exactly sum_payouts — that is the only money
                // the system created (fees are pure transfers between
                // player and owners, payouts are credited from nothing).
                // amusement_balance is fees − payouts and isn't a sound
                // basis: when fees > payouts > 0 it over-taxes, when
                // fees = payouts it under-taxes to 0.
                $deducted = 0.0;
                if ($memberCount > 0) {
                    $sumPayouts = (float) Transaction::where('amusement_id', $amusement->id)
                        ->where('type', 'payout')
                        ->sum('amount');
                    if ($sumPayouts > 0) {
                        // Floor so we never reclaim more than was created.
                        $deducted = floor($sumPayouts / $memberCount * 100) / 100;
                        if ($deducted > 0) {
                            foreach ($members as $member) {
                                $member->decrement('balance', $deducted);
                            }
                        }
                    }
                }

                $amusement->settled_at = now();
                $amusement->save();

                $details[] = [
                    'amusement_id' => $amusement->id,
                    'amusement_name' => $amusement->name,
                    'amusement_balance' => (float) $amusement->amusement_balance,
                    'deducted_per_member' => $deducted,
                    'member_count' => $memberCount,
                ];
            }
        });

        return response()->json([
            'amusements_settled' => count($details),
            'details' => $details,
        ]);
    }
}
