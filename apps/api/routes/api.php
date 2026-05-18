<?php

use App\Http\Controllers\ActivateUserController;
use App\Http\Controllers\AmusementController;
use App\Http\Controllers\Auth\AuthSessionController;
use App\Http\Controllers\ExchangeController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\IdentityTokenController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\ResetController;
use App\Http\Controllers\StampController;
use App\Http\Controllers\StoreAmusementController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UpdateUserInfoController;
use App\Http\Controllers\VoteController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// ── Status ─────────────────────────────────────────────────────────────
Route::get('/', fn () => response()->json([
    'name' => 'Tivoli CentralBank API',
    'status' => 'ok',
    'docs' => null,
]));

// ── Auth ───────────────────────────────────────────────────────────────
Route::post('/activate', [ActivateUserController::class, 'store']);
Route::post('/login', [AuthSessionController::class, 'store']);

// CSRF token endpoint — returns the current session's CSRF token in JSON so
// the SPA can read it across origins (document.cookie can't see the
// XSRF-TOKEN cookie when the API is on a different PSL subdomain).
Route::get('/csrf-token', fn () => response()->json(['csrf_token' => csrf_token()]));

// ── Transactions (amusement api_key in request body) ───────────────────
Route::get('/identity-tokens/{token}', [IdentityTokenController::class, 'show']);
Route::post('/transactions', [TransactionController::class, 'store']);
Route::post('/transactions/{id}/payout', [TransactionController::class, 'payout']);

// ── Authenticated user routes ──────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthSessionController::class, 'destroy']);

    // Identity tokens (short-lived, for amusement redirects)
    Route::post('/identity-tokens', [IdentityTokenController::class, 'store']);

    // User
    Route::get('/user', function (Request $request) {
        $user  = $request->user()->load('group');
        $group = $user->group;
        return response()->json([
            'id'          => $user->id,
            'name'        => $user->name,
            'balance'     => $user->balance,
            'stamp_count' => $user->stamps()->count(),
            'has_voted'   => $user->vote()->exists(),
            'group'       => $group ? [
                'id'           => $group->id,
                'name'         => $group->name,
                'is_admin'     => (bool) $group->is_admin,
                'member_count' => $group->users()->count(),
            ] : null,
        ]);
    });
    Route::patch('/user', [UpdateUserInfoController::class, 'update']);

    // Groups
    Route::get('/groups', [GroupController::class, 'index']);
    Route::get('/groups/{id}', [GroupController::class, 'show']);
    Route::patch('/groups/{id}', [GroupController::class, 'update']);

    // Amusements
    Route::get('/amusements', [AmusementController::class, 'index']);
    Route::post('/amusements', [StoreAmusementController::class, 'store']);
    Route::get('/amusements/{id}', [AmusementController::class, 'show']);
    Route::patch('/amusements/{id}', [AmusementController::class, 'update']);
    Route::delete('/amusements/{id}', [AmusementController::class, 'destroy']);
    Route::post('/amusements/{id}/regenerate-key', [AmusementController::class, 'regenerateKey']);
    Route::get('/amusements/{id}/transactions', [AmusementController::class, 'transactions']);
    Route::get('/amusements/{id}/stats', [AmusementController::class, 'stats']);

    // Stamps & exchanges
    Route::get('/stamps', [StampController::class, 'index']);
    Route::post('/exchanges', [ExchangeController::class, 'store']);

    // Votes
    Route::post('/votes', [VoteController::class, 'store']);

    // Leaderboard
    Route::get('/leaderboard', [LeaderboardController::class, 'show']);

    // Game reset (admin only)
    Route::post('/reset', [ResetController::class, 'store']);
});
