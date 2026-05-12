<?php

namespace App\Http\Controllers;

use App\Models\Amusement;
use App\Models\IdentityToken;
use App\Models\Stamp;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identity_token' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0'],
            'amusement_uuid' => ['required', 'string', 'uuid'],
        ]);

        $amusement = $request->attributes->get('amusement');

        if ($amusement->uuid !== $data['amusement_uuid']) {
            return response()->json(
                ['error' => 'amusement_uuid does not match the authenticated amusement'],
                403,
            );
        }

        $token = IdentityToken::where('token', $data['identity_token'])->first();

        if (!$token || !$token->isValid()) {
            return response()->json(['error' => 'Invalid or expired identity token'], 401);
        }

        $user = $token->user;

        if ($user->balance < $data['amount']) {
            return response()->json(['error' => 'Insufficient balance'], 402);
        }

        return DB::transaction(function () use ($user, $amusement, $data, $token) {
            $token->update(['consumed_at' => now()]);

            $user->decrement('balance', $data['amount']);
            $amusement->increment('amusement_balance', $data['amount']);

            $transaction = Transaction::create([
                'user_id' => $user->id,
                'amusement_id' => $amusement->id,
                'amount' => $data['amount'],
                'type' => 'fee',
            ]);

            $stamp = Stamp::generate($user->id);

            return response()->json([
                'id' => $transaction->id,
                'stamp' => $stamp,
            ], 201);
        });
    }

    public function payout(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $amusement = $request->attributes->get('amusement');

        $original = Transaction::find($id);

        if (!$original) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        if ($original->amusement_id !== $amusement->id) {
            return response()->json(['error' => 'Transaction does not belong to this amusement'], 403);
        }

        if ($original->type !== 'fee') {
            return response()->json(['error' => 'Only fee transactions can be paid out'], 400);
        }

        // Amusement balance is allowed to go negative; it's reconciled at
        // settle (group members absorb the debt).

        return DB::transaction(function () use ($original, $amusement, $data) {
            $amusement->decrement('amusement_balance', $data['amount']);
            $original->user->increment('balance', $data['amount']);

            $payout = Transaction::create([
                'user_id' => $original->user_id,
                'amusement_id' => $amusement->id,
                'amount' => $data['amount'],
                'type' => 'payout',
            ]);

            return response()->json([
                'id' => $payout->id,
                'original_transaction_id' => $original->id,
            ], 201);
        });
    }
}
