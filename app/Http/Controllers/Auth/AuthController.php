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
use OpenApi\Attributes as OA;

final class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AuthService $authService
    ) {}

    #[OA\Post(
        path: '/auth/register',
        summary: 'Register user baru',
        description: 'Mendaftarkan user baru dan langsung mengembalikan token Sanctum. Role default adalah mahasiswa jika tidak diisi.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Dela Fajar Mulia'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'dela@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'password123'),
                    new OA\Property(property: 'phone_number', type: 'string', nullable: true, example: '6288228893279'),
                    new OA\Property(property: 'role', type: 'string', enum: ['klien', 'pengurus_tefa', 'dosen', 'mahasiswa', 'jurusan'], example: 'mahasiswa'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Registrasi berhasil',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Registrasi berhasil'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                                new OA\Property(property: 'token', type: 'string', example: '1|raNd0mToKenStr1ng...'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validasi gagal (email sudah ada, password kurang kuat/tidak cocok, dll)'),
        ]
    )]
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

    #[OA\Post(
        path: '/auth/login',
        summary: 'Login user',
        description: 'Autentikasi dengan email & password. Mengembalikan user profile & Sanctum personal access token.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'dela.43325207@mhs.polines.ac.id'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: '43325207'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login berhasil',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Login berhasil'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                                new OA\Property(property: 'token', type: 'string', example: '1|raNd0mToKenStr1ng...'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Email atau password salah / format salah'),
        ]
    )]
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

    #[OA\Post(
        path: '/auth/logout',
        summary: 'Logout user',
        description: 'Mencabut (menghapus) token Sanctum yang sedang aktif digunakan.',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Logout berhasil',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Logout berhasil'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->ok(message: 'Logout berhasil');
    }

    #[OA\Get(
        path: '/auth/me',
        summary: 'Get Current Authenticated User',
        description: 'Mengambil data profil user yang sedang login beserta role-nya.',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Data user berhasil diambil',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'User profile'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function me(Request $request): JsonResponse
    {
        return $this->ok(
            message: 'User profile',
            data: new UserResource($request->user()->load('roles'))
        );
    }
}
