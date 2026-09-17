<?php

use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Auth\Controllers\MeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json(['status' => 'ok'], 200);
    });

    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/logout', [AuthController::class, 'logout'])
            ->middleware(['auth:sanctum', 'ability:access']);
    });

    Route::middleware(['auth:sanctum', 'ability:access'])->group(function () {
        Route::get('/me', [MeController::class, 'show']);
        Route::delete('/me', [MeController::class, 'destroy']);
    });
});
