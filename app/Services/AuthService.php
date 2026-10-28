<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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

    public function loginWithGoogle(string $idToken): array
    {
        try {
            $response = Http::timeout(10)->get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $idToken,
            ]);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'id_token' => ['Gagal menghubungi server verifikasi Google.'],
            ]);
        }

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'id_token' => ['Token Google tidak valid atau sudah kadaluarsa.'],
            ]);
        }

        $payload = $response->json();
        $expectedClientId = config('services.google.client_id');

        if (! empty($expectedClientId) && ($payload['aud'] ?? null) !== $expectedClientId) {
            throw ValidationException::withMessages([
                'id_token' => ['Google Client ID tidak cocok.'],
            ]);
        }

        if (empty($payload['email'])) {
            throw ValidationException::withMessages([
                'id_token' => ['Email tidak ditemukan dari akun Google.'],
            ]);
        }

        $user = User::firstOrCreate(
            ['email' => $payload['email']],
            [
                'name'     => $payload['name'] ?? $payload['email'],
                'password' => Hash::make(Str::random(32)),
            ]
        );

        if ($user->wasRecentlyCreated || $user->roles()->doesntExist()) {
            $user->assignRole('mahasiswa');
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
            'user'  => $user->load('roles'),
            'token' => $user->createToken('auth-token')->plainTextToken,
        ];
    }
}
