<?php

declare(strict_types=1);

namespace Tests\Feature;

use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    public function test_can_list_all_users(): void
    {
        $response = $this->getJson('/api/v1/users');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => ['id', 'name', 'email', 'role']
                ],
            ]);
    }

    public function test_can_create_a_user(): void
    {
        $payload = [
            'name'  => 'Bruce Wayne',
            'email' => 'bruce@wayne.corp',
            'role'  => 'admin',
        ];

        $response = $this->postJson('/api/v1/users', $payload);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Bruce Wayne')
            ->assertJsonPath('data.email', 'bruce@wayne.corp');
    }

    public function test_can_show_single_user(): void
    {
        $response = $this->getJson('/api/v1/users/1');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', 1);
    }

    public function test_show_non_existent_user_returns_404(): void
    {
        $response = $this->getJson('/api/v1/users/9999');

        $response->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'User not found');
    }

    public function test_can_update_a_user(): void
    {
        $payload = [
            'name' => 'John Updated',
        ];

        $response = $this->putJson('/api/v1/users/1', $payload);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'John Updated');
    }

    public function test_can_delete_a_user(): void
    {
        $response = $this->deleteJson('/api/v1/users/2');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'User deleted successfully');
    }
}
