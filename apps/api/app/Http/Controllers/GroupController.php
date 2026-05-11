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
        $groups = Group::withCount('users as member_count')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $groups]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $group = Group::with(['users:id,name,group_id', 'amusements'])->find($id);

        if (!$group) {
            return response()->json(['error' => 'Group not found'], 404);
        }

        if ($request->user()->group_id === $group->id) {
            $group->amusements->each(fn($a) => $a->makeVisible('access_key'));
        }

        return response()->json($group);
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
