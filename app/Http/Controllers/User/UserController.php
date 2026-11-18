<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\User\UserResource;
use App\Models\ClientPic;
use App\Models\ClientProfile;
use App\Models\User;
use App\Services\UserService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        parameters: [
            new OA\Parameter(
                name: 'role',
                in: 'query',
                required: false,
                description: 'Filter user berdasarkan role (misal: dosen, mahasiswa, klien)',
                schema: new OA\Schema(type: 'string', example: 'dosen')
            ),
        ],
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
    public function index(\Illuminate\Http\Request $request): JsonResponse
    {
        $role = $request->query('role');
        $users = $this->userService->getAll(is_string($role) ? $role : null);

        return $this->ok(
            message: 'Users retrieved successfully',
            data: UserResource::collection($users)
        );
    }

    #[OA\Get(
        path: '/users/company',
        summary: 'Data user dan perusahaan',
        description: 'Mengambil data user yang sedang login dari token (nama, email, no_hp) beserta nama perusahaan. Khusus role klien dan jurusan. Untuk role jurusan, `company_name` diisi nama jurusan.',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Data user dan perusahaan berhasil diambil',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Company profile retrieved successfully'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'name', type: 'string', example: 'Budi Santoso'),
                                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'budi.santoso@majubersamasentosa.co.id'),
                                new OA\Property(property: 'no_hp', type: 'string', nullable: true, example: '6281234567890'),
                                new OA\Property(property: 'company_name', type: 'string', nullable: true, example: 'PT. Maju Bersama Sentosa'),
                                new OA\Property(property: 'role', type: 'string', enum: ['klien', 'jurusan'], example: 'klien'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden (bukan klien/jurusan)'),
        ]
    )]
    public function company(Request $request): JsonResponse
    {
        $user = $request->user();

        $companyName = $user->hasRole('jurusan')
            ? $user->name
            : $user->clientPic?->clientProfile?->user?->name
                ?? $user->clientProfile?->user?->name;

        return $this->ok(
            message: 'Company profile retrieved successfully',
            data: [
                'name'         => $user->name,
                'email'        => $user->email,
                'no_hp'        => $user->phone_number,
                'company_name' => $companyName,
                'role'         => $user->getRoleNames()->first(),
            ]
        );
    }

    public function registerCompany(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                  => ['required', 'string', 'max:150'],
            'email'                 => ['required', 'email', 'unique:users,email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
            'phone_number'          => ['nullable', 'string', 'max:20'],
            'address'               => ['nullable', 'string', 'max:500'],
            'client_type'           => ['nullable', 'string', 'in:EXTERNAL,INTERNAL'],
            'pic_name'              => ['nullable', 'string', 'max:100'],
            'pic_email'             => ['nullable', 'email', 'unique:users,email'],
            'pic_phone'             => ['nullable', 'string', 'max:20'],
            'pic_position'          => ['nullable', 'string', 'max:100'],
            'pic_password'          => ['nullable', 'string', 'min:8'],
        ]);

        return DB::transaction(function () use ($validated) {
            $companyUser = User::create([
                'name'         => $validated['name'],
                'email'        => $validated['email'],
                'password'     => $validated['password'],
                'phone_number' => $validated['phone_number'] ?? null,
            ]);
            $companyUser->assignRole('klien');

            $clientProfile = ClientProfile::create([
                'user_id'     => $companyUser->id,
                'address'     => $validated['address'] ?? null,
                'client_type' => $validated['client_type'] ?? 'EXTERNAL',
            ]);

            if (!empty($validated['pic_name']) && !empty($validated['pic_email'])) {
                $picUser = User::create([
                    'name'         => $validated['pic_name'],
                    'email'        => $validated['pic_email'],
                    'password'     => $validated['pic_password'] ?? 'Password123!',
                    'phone_number' => $validated['pic_phone'] ?? null,
                ]);
                $picUser->assignRole('klien');

                ClientPic::create([
                    'client_profile_id' => $clientProfile->id,
                    'user_id'           => $picUser->id,
                    'position'          => $validated['pic_position'] ?? 'PIC',
                ]);
            }

            // ponytail: flat payload; expand when caller needs full relationship graph.
            return $this->created(
                message: 'Perusahaan berhasil didaftarkan.',
                data: [
                    'id'           => $companyUser->id,
                    'name'         => $companyUser->name,
                    'email'        => $companyUser->email,
                    'phone_number' => $companyUser->phone_number,
                    'address'      => $clientProfile->address,
                    'client_type'  => $clientProfile->client_type,
                    'role'         => 'klien',
                ]
            );
        });
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
        path: '/users/{user}',
        summary: 'Detail user',
        description: 'Mengambil detail data user spesifik berdasarkan ID.',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'user',
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
        path: '/users/{user}',
        summary: 'Update data user',
        description: 'Memperbarui informasi user yang sudah ada.',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'user',
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
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', description: 'Wajib jika password dikirim.', example: 'newpassword123'),
                    new OA\Property(property: 'phone_number', type: 'string', nullable: true, example: '628987654321'),
                    new OA\Property(property: 'role', type: 'string', description: 'Nama role yang tersedia di aplikasi.', example: 'mahasiswa'),
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
    #[OA\Patch(
        path: '/users/{user}',
        summary: 'Update data user (partial update)',
        description: 'Memperbarui informasi user yang sudah ada. Field bersifat opsional; password_confirmation wajib jika password dikirim.',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'user',
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
                    new OA\Property(property: 'name', type: 'string', maxLength: 100, example: 'John Updated'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john_updated@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', description: 'Gunakan bersama password_confirmation.'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                    new OA\Property(property: 'phone_number', type: 'string', nullable: true, maxLength: 20, example: '628987654321'),
                    new OA\Property(property: 'role', type: 'string', nullable: true, description: 'Nama role yang tersedia di aplikasi.', example: 'mahasiswa'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'User berhasil diupdate'),
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
        path: '/users/{user}',
        summary: 'Hapus user',
        description: 'Menghapus (soft delete) user berdasarkan ID.',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'user',
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
