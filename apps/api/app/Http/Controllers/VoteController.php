<?php

namespace App\Http\Controllers;

use App\Http\Requests\VoteRequest;
use Illuminate\Http\Request;

class VoteController extends Controller
{
    public function store(VoteRequest $request)
    {
        $user = $request->user();

        if ($user->vote) {
            return response()->json(['error' => 'User has already voted'], 400);
        }

        $vote = $user->vote()->create([
            'amusement_id' => $request->amusement_id,
        ]);

        return response()->json(['message' => 'Vote recorded', 'vote' => $vote], 201);
    }
}
