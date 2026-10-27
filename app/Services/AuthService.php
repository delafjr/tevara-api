<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function register(array $data): array
    {
        $user = User::create([
            'name'         => $data['name'],
            'email'        => $data['email'],
            'password'     => $data['password'],
            'phone_number' => $data['phone_number'] ?? null,
        ]);

        $user->assignRole($data['role'] ?? 'mahasiswa');

        return $this->authenticatedPayload($user);
    }

    public function login(array $credentials): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        return $this->authenticatedPayload($user);
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    private function authenticatedPayload(User $user): array
    {
        return [
            'user'  => $user->load(['roles', 'clientPic.clientProfile.user']),
            'token' => $user->createToken('auth-token')->plainTextToken,
        ];
    }
}
