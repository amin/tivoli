<?php

namespace App\Http\Controllers;

use App\Http\Requests\VoteRequest;
use App\Models\Vote;
use Illuminate\Http\JsonResponse;

class VoteController extends Controller
{
    public function store(VoteRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->vote()->exists()) {
            return response()->json(['message' => 'You have already voted.'], 409);
        }

        $user->vote()->create(['amusement_id' => $request->amusement_id]);

        return response()->json(['message' => 'Vote recorded'], 201);
    }
}
