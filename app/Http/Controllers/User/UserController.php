<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\User\UserResource;
use App\Services\UserService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

final class UserController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly UserService $userService
    ) {}

    #[OA\Get(
        path: '/users',
        summary: 'List semua user',
        description: 'Mengambil seluruh daftar user beserta roles dari database.',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Daftar user berhasil diambil',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Users retrieved successfully'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/User')
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(): JsonResponse
    {
        $users = $this->userService->getAll();

        return $this->ok(
            message: 'Users retrieved successfully',
            data: UserResource::collection($users)
        );
    }

    #[OA\Post(
        path: '/users',
        summary: 'Buat user baru',
        description: 'Membuat akun user baru langsung dari admin/pengurus.',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'password123'),
                    new OA\Property(property: 'phone_number', type: 'string', nullable: true, example: '628123456789'),
                    new OA\Property(property: 'role', type: 'string', enum: ['klien', 'pengurus_tefa', 'dosen', 'mahasiswa', 'jurusan'], example: 'mahasiswa'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'User berhasil dibuat',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'User created successfully'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validasi gagal'),
        ]
    )]
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->create($request->validated());

        return $this->created(
            message: 'User created successfully',
            data: new UserResource($user)
        );
    }

    #[OA\Get(
        path: '/users/{id}',
        summary: 'Detail user',
        description: 'Mengambil detail data user spesifik berdasarkan ID.',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID User',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Detail user ditemukan',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'User details retrieved'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'User tidak ditemukan'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $user = $this->userService->findById($id);

        if (!$user) {
            return $this->error(
                message: 'User not found',
                statusCode: Response::HTTP_NOT_FOUND
            );
        }

        return $this->ok(
            message: 'User details retrieved',
            data: new UserResource($user)
        );
    }

    #[OA\Put(
        path: '/users/{id}',
        summary: 'Update data user',
        description: 'Memperbarui informasi user yang sudah ada.',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID User',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'John Updated'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john_updated@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'newpassword123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'newpassword123'),
                    new OA\Property(property: 'phone_number', type: 'string', nullable: true, example: '628987654321'),
                    new OA\Property(property: 'role', type: 'string', enum: ['klien', 'pengurus_tefa', 'dosen', 'mahasiswa', 'jurusan'], example: 'mahasiswa'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'User berhasil diupdate',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'User updated successfully'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'User tidak ditemukan'),
            new OA\Response(response: 422, description: 'Validasi gagal'),
        ]
    )]
    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = $this->userService->update($id, $request->validated());

        if (!$user) {
            return $this->error(
                message: 'User not found',
                statusCode: Response::HTTP_NOT_FOUND
            );
        }

        return $this->ok(
            message: 'User updated successfully',
            data: new UserResource($user)
        );
    }

    #[OA\Delete(
        path: '/users/{id}',
        summary: 'Hapus user',
        description: 'Menghapus (soft delete) user berdasarkan ID.',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID User',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User berhasil dihapus',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'User deleted successfully'),
                        new OA\Property(property: 'data', type: 'null', example: null),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'User tidak ditemukan'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->userService->delete($id);

        if (!$deleted) {
            return $this->error(
                message: 'User not found',
                statusCode: Response::HTTP_NOT_FOUND
            );
        }

        return $this->ok(
            message: 'User deleted successfully',
            data: null
        );
    }
}
