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
        $data = $request->validated();

        $data['group_id'] = $request->user()->group_id();
        $data['access_key'] = Str::uuid();

        $amusement = Amusement::create($data);

        return response()->json([
            'message' => 'Amusement registered',
            'amusement' => $amusement,
        ], 201);
    }
}
