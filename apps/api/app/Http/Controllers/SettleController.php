<?php

namespace App\Http\Controllers;

use App\Models\Amusement;
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

                $deducted = 0.0;
                if ($amusement->amusement_balance < 0 && $memberCount > 0) {
                    $debt = abs((float) $amusement->amusement_balance);
                    $deducted = round($debt / $memberCount, 2);
                    foreach ($members as $member) {
                        $member->decrement('balance', $deducted);
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
