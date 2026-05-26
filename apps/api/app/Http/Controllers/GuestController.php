<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestController extends Controller
{
    public function show(): JsonResponse
    {
        $active = User::whereRaw('LOWER(name) = ?', ['guest'])
            ->where('is_active', true)
            ->exists();

        return response()->json(['active' => $active]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user()->load('group');

        if (!$user->group?->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $guest = User::whereRaw('LOWER(name) = ?', ['guest'])->first();

        if (!$guest) {
            return response()->json(['message' => 'Guest account not found'], 404);
        }

        $guest->is_active = $validated['is_active'];
        $guest->save();

        return response()->json(['active' => (bool) $guest->is_active]);
    }
}
