<?php

namespace App\Http\Controllers;

use App\Models\Stamp;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StampController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $stamps = Stamp::where('user_id', $validated['user_id'])
            ->whereNull('exchanged_at')
            ->get();

        return response()->json(['data' => $stamps]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id']
        ]);

        $stamp = Stamp::generate($validated['user_id'] ?? null);

        return response()->json($stamp, 201);
    }
}
