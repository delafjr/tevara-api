<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\User\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {

    // ── Public (guest) ─────────────────────────────────
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('login',    [AuthController::class, 'login'])->name('login');
    });

    // ── Protected ──────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('auth')->name('auth.')->group(function () {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('me',     [AuthController::class, 'me'])->name('me');
        });

        Route::apiResource('users', UserController::class);
    });
});
