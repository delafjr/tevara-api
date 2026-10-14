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

        // ── Mahasiswa only ─────────────────────────────
        Route::middleware('role:mahasiswa')->prefix('mahasiswa')->name('mahasiswa.')->group(function () {
            Route::get('dashboard', fn () => response()->json([
                'success' => true,
                'message' => 'Mahasiswa dashboard',
                'data'    => ['role' => 'mahasiswa'],
            ]))->name('dashboard');
        });

        // ── Dosen only ─────────────────────────────────
        Route::middleware('role:dosen')->prefix('dosen')->name('dosen.')->group(function () {
            Route::get('dashboard', fn () => response()->json([
                'success' => true,
                'message' => 'Dosen dashboard',
                'data'    => ['role' => 'dosen'],
            ]))->name('dashboard');
        });
    });
});
