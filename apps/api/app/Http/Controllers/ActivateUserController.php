<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ActivateUserController extends Controller
{
    public function store(ActivateUserRequest $request)
    {
        // Normalize name for case-insensitive lookup
        $requestedName = trim($request->name);

        // Case-insensitive name lookup (works for MySQL/Postgres/SQLite)
        $user = User::whereRaw('LOWER(name) = ?', [\Illuminate\Support\Str::lower($requestedName)])
            ->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($user->access_key !== null) {
            return response()->json(['message' => 'User already activated'], 400);
        }

        if ($user->startcode !== $request->startcode) {
            return response()->json(['message' => 'Invalid startcode'], 401);
        }

        // If everything seems fine, generate access key
        $accessKey = Str::uuid()->toString();

        // Save hashed access_key
        $user->access_key = Hash::make($accessKey);
        $user->save();

        return response()->json([
            'message' => 'Activation successful',
            'access_key' => $accessKey, // Plain text for user to save
        ]);
    }
}
