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
use Symfony\Component\HttpFoundation\Response;

final class UserController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly UserService $userService
    ) {}

    /**
     * GET /api/v1/users
     */
    public function index(): JsonResponse
    {
        $users = $this->userService->getAll();

        return $this->ok(
            message: 'Users retrieved successfully',
            data: UserResource::collection($users)
        );
    }

    /**
     * POST /api/v1/users
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->create($request->validated());

        return $this->created(
            message: 'User created successfully',
            data: new UserResource($user)
        );
    }

    /**
     * GET /api/v1/users/{id}
     */
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

    /**
     * PUT/PATCH /api/v1/users/{id}
     */
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

    /**
     * DELETE /api/v1/users/{id}
     */
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
