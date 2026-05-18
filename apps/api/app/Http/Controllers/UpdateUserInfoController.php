<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserInfoRequest;
use Illuminate\Http\Request;

class UpdateUserInfoController extends Controller
{
    public function update(UpdateUserInfoRequest $request)
    {
        $user = $request->user(); // From middleware

        $user->update($request->validated());

        return response()->json([
            'message' => 'User info updated',
            'user' => $user
        ]);
    }
}
