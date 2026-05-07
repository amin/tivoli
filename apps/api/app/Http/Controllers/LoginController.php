<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'access_key' => ['required', 'string', 'uuid'],
        ]);

        $name = trim($validated['name']);
        $accessKey = $validated['access_key'];

        // Case-insensitive lookup
        $user = User::whereRaw('LOWER(name) = ?', [Str::lower($name)])->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        if ($user->access_key === null) {
            return response()->json(['error' => 'User not activated'], 400);
        }

        if (!Hash::check($accessKey, $user->access_key)) {
            return response()->json(['error' => 'Invalid access key'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['error' => 'User is inactive'], 403);
        }

        return response()->json($user);
    }
}
