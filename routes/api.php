<?php

use App\Http\Controllers\Api\ProposalController;
use App\Http\Controllers\Api\ProposalReviewController;
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
            Route::get('dashboard', [\App\Http\Controllers\DashboardController::class, 'mahasiswa'])->name('dashboard');
        });

        // ── Dosen only ─────────────────────────────────
        Route::middleware('role:dosen')->prefix('dosen')->name('dosen.')->group(function () {
            Route::get('dashboard', [\App\Http\Controllers\DashboardController::class, 'dosen'])->name('dashboard');
        });

        // ── F-01: Proposal ─────────────────────────────
        // /proposals/mine HARUS didaftarkan sebelum /proposals/{id}
        Route::middleware('role:klien')->group(function () {
            Route::get('proposals/mine', [ProposalController::class, 'mine']);
            Route::post('proposals', [ProposalController::class, 'store']);
        });

        Route::middleware('role:pengurus_tefa|jurusan')->group(function () {
            Route::get('proposals', [ProposalController::class, 'index']);
        });

        Route::middleware('role:klien|pengurus_tefa|dosen|jurusan')->group(function () {
            Route::get('proposals/{id}', [ProposalController::class, 'show'])->whereNumber('id');
        });

        // ── F-02: Review (khusus Pengurus TeFa) ────────
        Route::middleware('role:pengurus_tefa')->group(function () {
            Route::patch('proposals/{id}/review', [ProposalReviewController::class, 'review'])->whereNumber('id');
            Route::patch('proposals/{id}/status', [ProposalReviewController::class, 'updateStatus'])->whereNumber('id');
        });
    });
});