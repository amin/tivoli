<?php

use App\Http\Controllers\ExchangeController;
use App\Http\Controllers\StampController;
use App\Http\Controllers\VictoryPointsController;
use Illuminate\Support\Facades\Route;

Route::get('/stamps', [StampController::class, 'index']);
Route::post('/stamps', [StampController::class, 'store']);
Route::post('/exchanges', [ExchangeController::class, 'store']);
Route::get('/victory-points', [VictoryPointsController::class, 'show']);
