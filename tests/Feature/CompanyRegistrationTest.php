<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CompanyRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['klien', 'pengurus_tefa', 'dosen', 'mahasiswa', 'jurusan'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_non_pengurus_tefa_forbidden_to_register_company(): void
    {
        $dosen = User::factory()->create();
        $dosen->assignRole('dosen');
        Sanctum::actingAs($dosen);

        $response = $this->postJson('/api/v1/users/company', [
            'name'                  => 'PT Maju Mundur',
            'email'                 => 'admin@majumundur.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_pengurus_tefa_can_register_company_with_profile_and_pic(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('pengurus_tefa');
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/users/company', [
            'name'                  => 'PT Nusantara Teknologi',
            'email'                 => 'corp@nusantara.id',
            'password'              => 'Secret12345!',
            'password_confirmation' => 'Secret12345!',
            'phone_number'          => '081234567890',
            'address'               => 'Jl. Merdeka No. 45',
            'client_type'           => 'EXTERNAL',
            'pic_name'              => 'Budi Santoso',
            'pic_email'             => 'budi@nusantara.id',
            'pic_position'          => 'Product Manager',
        ]);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'PT Nusantara Teknologi')
            ->assertJsonPath('data.email', 'corp@nusantara.id');

        $this->assertDatabaseHas('users', [
            'name'  => 'PT Nusantara Teknologi',
            'email' => 'corp@nusantara.id',
        ]);

        $companyUser = User::where('email', 'corp@nusantara.id')->first();
        $this->assertNotNull($companyUser);
        $this->assertTrue($companyUser->hasRole('klien'));
        $this->assertNotNull($companyUser->clientProfile);
        $this->assertEquals('Jl. Merdeka No. 45', $companyUser->clientProfile->address);

        $this->assertDatabaseHas('users', [
            'name'  => 'Budi Santoso',
            'email' => 'budi@nusantara.id',
        ]);
        $this->assertDatabaseHas('client_pics', [
            'client_profile_id' => $companyUser->clientProfile->id,
            'position'          => 'Product Manager',
        ]);
    }

    public function test_pengurus_tefa_can_list_companies_and_register_standalone_pic(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('pengurus_tefa');
        Sanctum::actingAs($admin);

        // Setup existing company
        $companyUser = User::factory()->create(['name' => 'PT Semen Maju']);
        $companyUser->assignRole('klien');
        $profile = \App\Models\ClientProfile::create([
            'user_id' => $companyUser->id,
            'address' => 'Semarang',
        ]);

        // 1. List companies
        $listRes = $this->getJson('/api/v1/companies');
        $listRes->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.name', 'PT Semen Maju')
            ->assertJsonPath('data.0.id', $profile->id);

        // 2. Register PIC for existing company
        $picRes = $this->postJson('/api/v1/users/pic', [
            'client_profile_id'     => $profile->id,
            'name'                  => 'Rian Firmansyah',
            'email'                 => 'rian@semenmaju.com',
            'position'              => 'Chief Technology Officer',
            'phone_number'          => '08999999999',
            'password'              => 'Secret12345!',
            'password_confirmation' => 'Secret12345!',
        ]);

        $picRes->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Rian Firmansyah')
            ->assertJsonPath('data.position', 'Chief Technology Officer');

        $this->assertDatabaseHas('users', [
            'name'  => 'Rian Firmansyah',
            'email' => 'rian@semenmaju.com',
        ]);

        $this->assertDatabaseHas('client_pics', [
            'client_profile_id' => $profile->id,
            'position'          => 'Chief Technology Officer',
        ]);
    }
}
