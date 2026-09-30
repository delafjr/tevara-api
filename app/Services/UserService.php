<?php

declare(strict_types=1);

namespace App\Services;

class UserService
{
    /**
     * Dummy in-memory users storage (simulasi database).
     *
     * @var array<int, array<string, mixed>>
     */
    private static array $users = [
        [
            'id'    => 1,
            'name'  => 'John Doe',
            'email' => 'john@example.com',
            'role'  => 'admin',
        ],
        [
            'id'    => 2,
            'name'  => 'Jane Smith',
            'email' => 'jane@example.com',
            'role'  => 'member',
        ],
    ];

    /**
     * Get all users.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        return array_values(self::$users);
    }

    /**
     * Find user by ID.
     *
     * @param  int  $id
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        foreach (self::$users as $user) {
            if ($user['id'] === $id) {
                return $user;
            }
        }

        return null;
    }

    /**
     * Create a new user.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $newId = count(self::$users) > 0 ? max(array_column(self::$users, 'id')) + 1 : 1;

        $user = [
            'id'    => $newId,
            'name'  => $data['name'],
            'email' => $data['email'],
            'role'  => $data['role'] ?? 'member',
        ];

        self::$users[] = $user;

        return $user;
    }

    /**
     * Update an existing user.
     *
     * @param  int  $id
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public function update(int $id, array $data): ?array
    {
        foreach (self::$users as $key => $user) {
            if ($user['id'] === $id) {
                self::$users[$key] = array_merge($user, $data);
                return self::$users[$key];
            }
        }

        return null;
    }

    /**
     * Delete user by ID.
     *
     * @param  int  $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        foreach (self::$users as $key => $user) {
            if ($user['id'] === $id) {
                unset(self::$users[$key]);
                return true;
            }
        }

        return false;
    }
}
