<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAmusementRequest;
use App\Models\Amusement;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StoreAmusementController extends Controller
{
    public function store(StoreAmusementRequest $request)
    {
        $user = $request->user();

        if (!$user->group_id) {
            return response()->json([
                'error' => 'Your user is not assigned to a group',
            ], 400);
        }

        $data = $request->validated();
        $data['group_id'] = $user->group_id;
        $data['access_key'] = (string) Str::uuid();

        $amusement = Amusement::forceCreate($data);

        return response()->json([
            'message' => 'Amusement registered. Save the access_key — it is only shown here.',
            'amusement' => $amusement->makeVisible('access_key'),
        ], 201);
    }
}
