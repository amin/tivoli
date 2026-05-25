<?php

namespace App\Http\Controllers;

use App\Models\Amusement;
use App\Models\IdentityToken;
use App\Models\Stamp;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResetController extends Controller
{
    private const STARTING_BALANCE = 25.00;
    private const GUEST_STARTING_BALANCE = 90000.00;

    public function store(Request $request): JsonResponse
    {
        $user = $request->user()->load('group');

        if (!$user->group?->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        Stamp::query()->delete();
        Vote::query()->delete();
        Transaction::query()->delete();
        IdentityToken::query()->delete();
        User::whereRaw('LOWER(name) != ?', ['guest'])->update(['balance' => self::STARTING_BALANCE]);
        User::whereRaw('LOWER(name) = ?', ['guest'])->update(['balance' => self::GUEST_STARTING_BALANCE]);
        Amusement::query()->update(['amusement_balance' => 0, 'settled_at' => null]);

        return response()->json(['message' => 'Game reset successfully']);
    }
}
