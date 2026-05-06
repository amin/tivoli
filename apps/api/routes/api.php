<?php

use App\Http\Controllers\ActivateUserController;
use App\Http\Controllers\ExchangeController;
use App\Http\Controllers\StampController;
use App\Http\Controllers\VictoryPointsController;
use App\Http\Middleware\AccessKeyAuth;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/stamps', [StampController::class, 'index']);
Route::post('/stamps', [StampController::class, 'store']);
Route::post('/exchanges', [ExchangeController::class, 'store']);
Route::get('/victory-points', [VictoryPointsController::class, 'show']);
Route::post('/activate', [ActivateUserController::class, 'store']);

Route::get('/user', function (Request $request) {
    return response()->json($request->user());
})->middleware(AccessKeyAuth::class);
