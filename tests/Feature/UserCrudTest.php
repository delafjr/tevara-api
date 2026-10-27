<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $authAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $roles = ['klien', 'pengurus_tefa', 'dosen', 'mahasiswa', 'jurusan'];
        foreach ($roles as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }

        $this->authAdmin = User::factory()->create([
            'name'  => 'Admin User',
            'email' => 'admin@tevara.com',
        ]);
        $this->authAdmin->assignRole('pengurus_tefa');
    }

    public function test_can_list_all_users(): void
    {
        Sanctum::actingAs($this->authAdmin);

        $response = $this->getJson('/api/v1/users');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => ['id', 'name', 'email', 'phone_number', 'roles', 'is_pm', 'created_at']
                ],
            ]);
    }

    public function test_can_create_a_user(): void
    {
        Sanctum::actingAs($this->authAdmin);

        $payload = [
            'name'                  => 'Bruce Wayne',
            'email'                 => 'bruce@wayne.corp',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role'                  => 'mahasiswa',
        ];

        $response = $this->postJson('/api/v1/users', $payload);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Bruce Wayne')
            ->assertJsonPath('data.email', 'bruce@wayne.corp');
    }

    public function test_can_show_single_user(): void
    {
        Sanctum::actingAs($this->authAdmin);

        $targetUser = User::factory()->create(['name' => 'Target User']);

        $response = $this->getJson("/api/v1/users/{$targetUser->id}");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $targetUser->id);
    }

    public function test_show_non_existent_user_returns_404(): void
    {
        Sanctum::actingAs($this->authAdmin);

        $response = $this->getJson('/api/v1/users/9999');

        $response->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'User not found');
    }

    public function test_can_update_a_user(): void
    {
        Sanctum::actingAs($this->authAdmin);

        $targetUser = User::factory()->create(['name' => 'John Original']);

        $payload = [
            'name' => 'John Updated',
        ];

        $response = $this->putJson("/api/v1/users/{$targetUser->id}", $payload);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'John Updated');
    }

    public function test_can_delete_a_user(): void
    {
        Sanctum::actingAs($this->authAdmin);

        $targetUser = User::factory()->create();

        $response = $this->deleteJson("/api/v1/users/{$targetUser->id}");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'User deleted successfully');

        $this->assertSoftDeleted('users', [
            'id' => $targetUser->id,
        ]);
    }
}
