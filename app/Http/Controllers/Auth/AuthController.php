<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\User\UserResource;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AuthService $authService
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return $this->created(
            message: 'Registrasi berhasil',
            data: [
                'user'  => new UserResource($result['user']),
                'token' => $result['token'],
            ]
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        return $this->ok(
            message: 'Login berhasil',
            data: [
                'user'  => new UserResource($result['user']),
                'token' => $result['token'],
            ]
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->ok(message: 'Logout berhasil');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->ok(
            message: 'User profile',
            data: new UserResource($request->user()->load('roles'))
        );
    }
}
