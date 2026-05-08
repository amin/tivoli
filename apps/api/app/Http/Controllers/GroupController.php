<?php

namespace App\Http\Controllers;

use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\RenameGroupRequest;

class GroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function update(RenameGroupRequest $request, int $id): JsonResponse
    {
        // Use the requesting user's group as target. Ensure user can only rename its own group
        $user = $request->user();

        if (!$user || !$user->group_id) {
            return response()->json(['error' => 'User does not belong to a group'], 403);
        }

        $group = Group::find($user->group_id);
        if (!$group) {
            return response()->json(['error' => 'Group not found'], 404);
        }

        $data = $request->validated();
        $group->name = $data['name'];
        $group->save();

        return response()->json(['message' => 'Group renamed', 'group' => $group]);
    }
}
