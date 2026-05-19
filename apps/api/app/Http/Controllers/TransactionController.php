<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayoutTransactionRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Models\Amusement;
use App\Models\IdentityToken;
use App\Models\Stamp;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;

use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $data = $request->validated();

        $amusement = Amusement::where('api_key', $data['api_key'])->first();

        if (!$amusement) {
            return response()->json(['message' => 'Invalid api_key'], 401);
        }

        $token = IdentityToken::where('token', $data['identity_token'])->first();

        if (!$token || !$token->isValid()) {
            return response()->json(['message' => 'Invalid or expired identity token'], 401);
        }

        $user = $token->user;

        if ($user->balance < $data['amount']) {
            return response()->json(['message' => 'Insufficient balance'], 402);
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

            $stamp = Stamp::generate($user->id, $amusement->id);

            return response()->json([
                'id' => $transaction->id,
                'stamp' => $stamp,
            ], 201);

        });

    }

    public function payout(PayoutTransactionRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();

        $amusement = Amusement::where('api_key', $data['api_key'])->first();

        if (!$amusement) {
            return response()->json(['message' => 'Invalid api_key'], 401);
        }

        $original = Transaction::find($id);

        if (!$original) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        if ($original->amusement_id !== $amusement->id) {
            return response()->json(['message' => 'Transaction does not belong to this amusement'], 403);
        }

        if ($original->type !== 'fee') {
            return response()->json(['message' => 'Only fee transactions can be paid out'], 400);
        }

        if ($amusement->type === 'attraction') {
            return response()->json(['message' => 'Attractions cannot pay out'], 409);
        }

        if ($original->settled_at !== null) {
            return response()->json(
                ['message' => "Transaction #{$original->id} has already been paid out"],
                409,
            );
        }

         // Amusement balance is allowed to go negative; it's reconciled at
        // settle (group members absorb the debt).

        return DB::transaction(function () use ($original, $amusement, $data) {
            $amusement->decrement('amusement_balance', $data['amount']);
            $original->user->increment('balance', $data['amount']);
            $original->update(['settled_at' => now()]);

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