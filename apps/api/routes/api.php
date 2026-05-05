<?php

use App\Http\Controllers\ExchangeController;
use App\Http\Controllers\StampController;
use Illuminate\Support\Facades\Route;

Route::get('/stamps', [StampController::class, 'index']);
Route::post('/stamps', [StampController::class, 'store']);
Route::post('/exchanges', [ExchangeController::class, 'store']);
