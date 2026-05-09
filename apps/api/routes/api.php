<?php

use App\Http\Controllers\ActivateUserController;
use App\Http\Controllers\AmusementController;
use App\Http\Controllers\ExchangeController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\IdentityTokenController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\SettleController;
use App\Http\Controllers\StampController;
use App\Http\Controllers\StoreAmusementController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UpdateUserInfoController;
use App\Http\Controllers\VictoryPointsController;
use App\Http\Controllers\VoteController;
use App\Http\Middleware\AccessKeyAuth;
use App\Http\Middleware\AmusementApiKeyAuth;
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
Route::post('/auth/login', [LoginController::class, 'store']);

// ── Transactions (amusement API key auth) ──────────────────────────────
Route::middleware(AmusementApiKeyAuth::class)->group(function () {
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::post('/transactions/{id}/payout', [TransactionController::class, 'payout']);
});

// ── Authenticated user routes ──────────────────────────────────────────
Route::middleware(AccessKeyAuth::class)->group(function () {
    // Identity tokens (short-lived, for amusement redirects)
    Route::post('/identity-tokens', [IdentityTokenController::class, 'store']);

    // User
    Route::get('/me', [MeController::class, 'show']);
    Route::patch('/me', [UpdateUserInfoController::class, 'update']);

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

    // Stamps & exchanges
    Route::get('/stamps', [StampController::class, 'index']);
    Route::post('/exchanges', [ExchangeController::class, 'store']);

    // Votes
    Route::post('/votes', [VoteController::class, 'store']);

    // Settlement & leaderboard
    Route::post('/settle', [SettleController::class, 'store']);
    Route::get('/leaderboard', [LeaderboardController::class, 'show']);

    // Existing utility route (kept; not in spec)
    Route::get('/user', fn(Request $request) => response()->json($request->user()));
});

// Existing routes not in the spec (kept untouched)
Route::post('/stamps', [StampController::class, 'store']);
Route::get('/victory-points', [VictoryPointsController::class, 'show']);
