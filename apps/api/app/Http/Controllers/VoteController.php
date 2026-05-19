<?php

namespace App\Http\Controllers;

use App\Http\Requests\VoteRequest;
use App\Models\Amusement;
use App\Models\Vote;
use Illuminate\Http\JsonResponse;

class VoteController extends Controller
{
    public function store(VoteRequest $request): JsonResponse
    {
        $user = $request->user()->load('group');

        if ($user->vote()->exists()) {
            return response()->json(['message' => 'You have already voted.'], 409);
        }

        $amusement = Amusement::find($request->amusement_id);
        if ($amusement && $user->group_id !== null && $amusement->group_id === $user->group_id) {
            return response()->json(['message' => 'You cannot vote for your own amusement.'], 403);
        }

        $user->vote()->create(['amusement_id' => $request->amusement_id]);

        return response()->json(['message' => 'Vote recorded'], 201);
    }
}
