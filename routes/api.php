<?php
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AtmController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/atm/withdraw',         [AtmController::class, 'withdraw']);
    Route::get('/atm/balance',           [AtmController::class, 'balance']);
    Route::get('/atm/transactions',      [AtmController::class, 'transactions']);
});
