<?php

use App\Http\Controllers\Api\MasterDataController;
use App\Http\Controllers\Api\ProposalContractController;
use App\Http\Controllers\Api\ProposalController;
use App\Http\Controllers\Api\ProposalReviewController;
use App\Http\Controllers\Api\ProposalTeamController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\User\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {

    // ── Public (guest) ─────────────────────────────────
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('login',    [AuthController::class, 'login'])->name('login');
        Route::post('google',   [AuthController::class, 'google'])->name('google');
    });

    Route::get('categories', [MasterDataController::class, 'categories'])->name('categories.index');

    // ── Protected ──────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('auth')->name('auth.')->group(function () {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('me',     [AuthController::class, 'me'])->name('me');
        });

        // Data user + perusahaan (khusus klien & jurusan). Harus sebelum apiResource agar tidak tertangkap /users/{id}
        Route::middleware('role:klien|jurusan')->group(function () {
            Route::get('users/company', [UserController::class, 'company'])->name('users.company');
        });

        Route::apiResource('users', UserController::class);

        // ── Master Data ────────────────────────────────
        Route::prefix('master')->name('master.')->group(function () {
            Route::get('project-roles', [MasterDataController::class, 'projectRoles'])->name('project-roles');
            Route::get('proposal-categories', [MasterDataController::class, 'proposalCategories'])->name('proposal-categories');
            Route::get('proposal-statuses', [MasterDataController::class, 'proposalStatuses'])->name('proposal-statuses');
            Route::get('task-categories', [MasterDataController::class, 'taskCategories'])->name('task-categories');
        });

        // ── Mahasiswa only ─────────────────────────────
        Route::middleware('role:mahasiswa')->prefix('mahasiswa')->name('mahasiswa.')->group(function () {
            Route::get('dashboard', [\App\Http\Controllers\DashboardController::class, 'mahasiswa'])->name('dashboard');
        });

        // ── Dosen only ─────────────────────────────────
        Route::middleware('role:dosen')->prefix('dosen')->name('dosen.')->group(function () {
            Route::get('dashboard', [\App\Http\Controllers\DashboardController::class, 'dosen'])->name('dashboard');
        });

        // ── Klien only ─────────────────────────────────
        Route::middleware('role:klien')->prefix('klien')->name('klien.')->group(function () {
            Route::get('dashboard', [\App\Http\Controllers\DashboardController::class, 'klien'])->name('dashboard');
        });

        // ── Pengurus TeFa only ─────────────────────────
        Route::middleware('role:pengurus_tefa')->prefix('tefa')->name('tefa.')->group(function () {
            Route::get('dashboard', [\App\Http\Controllers\DashboardController::class, 'tefa'])->name('dashboard');
        });

        // ── Jurusan only ───────────────────────────────
        Route::middleware('role:jurusan')->prefix('jurusan')->name('jurusan.')->group(function () {
            Route::get('dashboard', [\App\Http\Controllers\DashboardController::class, 'jurusan'])->name('dashboard');
        });

        // ── F-01: Proposal ─────────────────────────────
        // /proposals/mine HARUS didaftarkan sebelum /proposals/{id}
        Route::middleware('role:klien|jurusan')->group(function () {
            Route::get('proposals/mine', [ProposalController::class, 'mine']);
            Route::post('proposals', [ProposalController::class, 'store']);
        });

        Route::middleware('role:pengurus_tefa')->group(function () {
            Route::get('proposals', [ProposalController::class, 'index']);
            Route::get('proposals/pending', [ProposalController::class, 'pending']);
            Route::get('proposals/{id}/pending', [ProposalController::class, 'pendingDetail'])->whereNumber('id');
        });

        Route::middleware('role:klien|pengurus_tefa|dosen|jurusan')->group(function () {
            Route::get('proposals/{id}', [ProposalController::class, 'show'])->whereNumber('id');
        });

        // ── F-02: Review (khusus Pengurus TeFa) ────────
        Route::middleware('role:pengurus_tefa')->group(function () {
            Route::patch('proposals/{id}/review', [ProposalReviewController::class, 'review'])->whereNumber('id');
            Route::patch('proposals/{id}/status', [ProposalReviewController::class, 'updateStatus'])->whereNumber('id');
        });

        // ── F-03: SPK / Kontrak (khusus Pengurus TeFa) ──
        Route::middleware('role:pengurus_tefa')->group(function () {
            Route::match(['patch', 'post'], 'proposals/{id}/contract', [ProposalContractController::class, 'updateContract'])->whereNumber('id');
            Route::match(['patch', 'post'], 'proposals/{id}/contract/sign', [ProposalContractController::class, 'sign'])->whereNumber('id');
            Route::patch('proposals/{id}/contract/pm', [ProposalContractController::class, 'assignPm'])->whereNumber('id');
        });

        // ── F-04: Penugasan Tim ─────────────────────────
        Route::middleware('role:pengurus_tefa|dosen|mahasiswa')->group(function () {
            Route::get('proposals/{id}/team', [ProposalTeamController::class, 'index'])->whereNumber('id');
        });

        Route::middleware('role:pengurus_tefa')->group(function () {
            Route::post('proposals/{id}/team', [ProposalTeamController::class, 'store'])->whereNumber('id');
            Route::put('proposals/{id}/team/{memberId}', [ProposalTeamController::class, 'update'])->whereNumber('id')->whereNumber('memberId');
            Route::delete('proposals/{id}/team/{memberId}', [ProposalTeamController::class, 'destroy'])->whereNumber('id')->whereNumber('memberId');
        });

        // ── F-05: Tasks & Progress Report ───────────────
        Route::middleware('role:pengurus_tefa|dosen|mahasiswa')->group(function () {
            Route::get('proposals/{id}/tasks', [TaskController::class, 'index'])->whereNumber('id');
            Route::get('proposals/{id}/tasks/summary', [TaskController::class, 'summary'])->whereNumber('id');
            Route::get('proposals/{id}/tasks/{taskId}', [TaskController::class, 'show'])->whereNumber('id')->whereNumber('taskId');
        });

        Route::middleware('role:dosen|pengurus_tefa')->group(function () {
            Route::post('proposals/{id}/tasks', [TaskController::class, 'store'])->whereNumber('id');
            Route::put('proposals/{id}/tasks/{taskId}', [TaskController::class, 'update'])->whereNumber('id')->whereNumber('taskId');
            Route::patch('proposals/{id}/tasks/{taskId}', [TaskController::class, 'update'])->whereNumber('id')->whereNumber('taskId');
            Route::delete('proposals/{id}/tasks/{taskId}', [TaskController::class, 'destroy'])->whereNumber('id')->whereNumber('taskId');
        });

        Route::middleware('role:mahasiswa')->group(function () {
            Route::match(['patch', 'post'], 'proposals/{id}/tasks/{taskId}/progress', [TaskController::class, 'updateProgress'])
                ->whereNumber('id')
                ->whereNumber('taskId');
        });

        Route::middleware('role:dosen')->group(function () {
            Route::patch('proposals/{id}/tasks/{taskId}/review', [TaskController::class, 'review'])
                ->whereNumber('id')
                ->whereNumber('taskId');
        });
    });
});