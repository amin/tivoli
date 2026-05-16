<?php

namespace App\Http\Controllers;

use App\Models\Stamp;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResetController extends Controller
{
    private const STARTING_BALANCE = 25.00;

    public function store(Request $request): JsonResponse
    {
        $user = $request->user()->load('group');

        if (!$user->group?->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        Stamp::query()->delete();
        Vote::query()->delete();
        User::query()->update(['balance' => self::STARTING_BALANCE]);

        return response()->json(['message' => 'Game reset successfully']);
    }
}
