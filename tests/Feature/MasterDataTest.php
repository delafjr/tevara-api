<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ProjectRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'pengurus_tefa', 'guard_name' => 'web']);
        ProjectRole::firstOrCreate(['name' => 'UI/UX']);
        ProjectRole::firstOrCreate(['name' => 'Frontend']);
        ProjectRole::firstOrCreate(['name' => 'Backend']);
    }

    public function test_can_list_project_roles(): void
    {
        $user = User::factory()->create();
        $user->assignRole('pengurus_tefa');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/master/project-roles');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    }
}
