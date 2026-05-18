<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivateUserRequest;
use App\Http\Requests\UpdateUserInfoRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function store(ActivateUserRequest $request): JsonResponse
    {
        $requestedName = trim($request->name);

        $user = User::whereRaw('LOWER(name) = ?', [Str::lower($requestedName)])
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

        $accessKey = Str::uuid()->toString();

        $user->access_key = Hash::make($accessKey);
        $user->save();

        return response()->json([
            'message' => 'Activation successful',
            'access_key' => $accessKey,
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('group');
        $group = $user->group;

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'balance' => $user->balance,
            'stamp_count' => $user->stamps()->count(),
            'has_voted' => $user->vote()->exists(),
            'group' => $group ? [
                'id' => $group->id,
                'name' => $group->name,
                'is_admin' => (bool) $group->is_admin,
                'member_count' => $group->users()->count(),
            ] : null,
        ]);
    }

    public function update(UpdateUserInfoRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return response()->json([
            'message' => 'User info updated',
            'user' => $user,
        ]);
    }
}
