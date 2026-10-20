<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class UserService
{
    public function getAll(): Collection
    {
        return User::with('roles')->get();
    }

    public function findById(int $id): ?User
    {
        return User::with('roles')->find($id);
    }

    public function create(array $data): User
    {
        $user = User::create($data);

        if (isset($data['role'])) {
            $user->assignRole($data['role']);
        }

        return $user->load('roles');
    }

    public function update(int $id, array $data): ?User
    {
        $user = User::find($id);

        if (! $user) {
            return null;
        }

        $user->update($data);

        if (isset($data['role'])) {
            $user->syncRoles($data['role']);
        }

        return $user->load('roles');
    }

    public function delete(int $id): bool
    {
        $user = User::find($id);

        if (! $user) {
            return false;
        }

        return (bool) $user->delete();
    }
}
