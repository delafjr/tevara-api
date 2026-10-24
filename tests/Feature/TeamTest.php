<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ProjectRole;
use App\Models\ProjectTeam;
use App\Models\Proposal;
use App\Models\ProposalCategory;
use App\Models\ProposalStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    private User $pengurus;
    private User $dosenPm;
    private User $dosenOther;
    private User $mhs1;
    private User $mhs2;
    private User $mhsOther;
    private Proposal $proposal;
    private ProjectRole $roleFrontend;
    private ProjectRole $roleBackend;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup Roles
        $roles = ['klien', 'pengurus_tefa', 'dosen', 'mahasiswa', 'jurusan'];
        foreach ($roles as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }

        // Setup Statuses
        $statuses = [
            'Draft', 'Submitted', 'Under Review', 'Revision Required',
            'Approved', 'Rejected', 'Active', 'Testing', 'Handover', 'Completed',
        ];
        foreach ($statuses as $s) {
            ProposalStatus::firstOrCreate(['name' => $s]);
        }

        // Setup Category
        $cat = ProposalCategory::firstOrCreate(['name' => 'Web']);

        // Setup Project Roles
        $this->roleFrontend = ProjectRole::firstOrCreate(['name' => 'Frontend']);
        $this->roleBackend  = ProjectRole::firstOrCreate(['name' => 'Backend']);

        // Setup Users
        $this->pengurus = User::factory()->create(['name' => 'Pengurus TeFa']);
        $this->pengurus->assignRole('pengurus_tefa');

        $this->dosenPm = User::factory()->create(['name' => 'Dosen PM']);
        $this->dosenPm->assignRole('dosen');

        $this->dosenOther = User::factory()->create(['name' => 'Dosen Lain']);
        $this->dosenOther->assignRole('dosen');

        $this->mhs1 = User::factory()->create(['name' => 'Mahasiswa Satu']);
        $this->mhs1->assignRole('mahasiswa');

        $this->mhs2 = User::factory()->create(['name' => 'Mahasiswa Dua']);
        $this->mhs2->assignRole('mahasiswa');

        $this->mhsOther = User::factory()->create(['name' => 'Mahasiswa Lain']);
        $this->mhsOther->assignRole('mahasiswa');

        $klien = User::factory()->create(['name' => 'Klien']);
        $klien->assignRole('klien');

        // Setup Proposal
        $this->proposal = Proposal::create([
            'client_id'        => $klien->id,
            'title'            => 'Proyek PBL TeFa',
            'background'       => str_repeat('Latar belakang proposal pbl tefa polines. ', 3),
            'category_id'      => $cat->id,
            'desired_deadline' => now()->addDays(40)->toDateString(),
            'status_id'        => 7, // Active
            'pm_id'            => $this->dosenPm->id,
        ]);
    }

    public function test_pengurus_can_add_student_to_team(): void
    {
        Sanctum::actingAs($this->pengurus);

        $payload = [
            'student_id' => $this->mhs1->id,
            'role_id'    => $this->roleFrontend->id,
            'joined_at'  => '2026-11-01',
        ];

        $response = $this->postJson("/api/v1/proposals/{$this->proposal->id}/team", $payload);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Anggota tim berhasil ditambahkan')
            ->assertJsonPath('data.user_id', $this->mhs1->id)
            ->assertJsonPath('data.role_id', $this->roleFrontend->id)
            ->assertJsonPath('data.student.name', $this->mhs1->name);

        $this->assertDatabaseHas('project_teams', [
            'proposal_id' => $this->proposal->id,
            'user_id'     => $this->mhs1->id,
            'role_id'     => $this->roleFrontend->id,
        ]);
    }

    public function test_cannot_add_same_student_twice(): void
    {
        Sanctum::actingAs($this->pengurus);

        ProjectTeam::create([
            'proposal_id' => $this->proposal->id,
            'user_id'     => $this->mhs1->id,
            'role_id'     => $this->roleFrontend->id,
            'joined_at'   => '2026-11-01',
        ]);

        $response = $this->postJson("/api/v1/proposals/{$this->proposal->id}/team", [
            'student_id' => $this->mhs1->id,
            'role_id'    => $this->roleBackend->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', "Mahasiswa {$this->mhs1->name} sudah terdaftar dalam tim proyek ini.");
    }

    public function test_cannot_add_non_mahasiswa_to_team(): void
    {
        Sanctum::actingAs($this->pengurus);

        // dosen is not a mahasiswa
        $response = $this->postJson("/api/v1/proposals/{$this->proposal->id}/team", [
            'student_id' => $this->dosenPm->id,
            'role_id'    => $this->roleFrontend->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_batch_add_students_to_team(): void
    {
        Sanctum::actingAs($this->pengurus);

        $payload = [
            'members' => [
                [
                    'student_id' => $this->mhs1->id,
                    'role_id'    => $this->roleFrontend->id,
                    'joined_at'  => '2026-11-01',
                ],
                [
                    'student_id' => $this->mhs2->id,
                    'role_id'    => $this->roleBackend->id,
                    'joined_at'  => '2026-11-01',
                ],
            ],
        ];

        $response = $this->postJson("/api/v1/proposals/{$this->proposal->id}/team", $payload);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Anggota tim (batch) berhasil ditambahkan')
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('project_teams', ['proposal_id' => $this->proposal->id, 'user_id' => $this->mhs1->id]);
        $this->assertDatabaseHas('project_teams', ['proposal_id' => $this->proposal->id, 'user_id' => $this->mhs2->id]);
    }

    public function test_authorized_users_can_view_team(): void
    {
        ProjectTeam::create([
            'proposal_id' => $this->proposal->id,
            'user_id'     => $this->mhs1->id,
            'role_id'     => $this->roleFrontend->id,
            'joined_at'   => '2026-11-01',
        ]);

        // Pengurus can view
        Sanctum::actingAs($this->pengurus);
        $resPengurus = $this->getJson("/api/v1/proposals/{$this->proposal->id}/team");
        $resPengurus->assertStatus(200)->assertJsonCount(1);

        // PM dosen can view
        Sanctum::actingAs($this->dosenPm);
        $resPm = $this->getJson("/api/v1/proposals/{$this->proposal->id}/team");
        $resPm->assertStatus(200)->assertJsonCount(1);

        // Member mahasiswa can view
        Sanctum::actingAs($this->mhs1);
        $resMhs = $this->getJson("/api/v1/proposals/{$this->proposal->id}/team");
        $resMhs->assertStatus(200)->assertJsonCount(1);

        // Non-PM dosen cannot view
        Sanctum::actingAs($this->dosenOther);
        $this->getJson("/api/v1/proposals/{$this->proposal->id}/team")->assertStatus(403);

        // Non-member mahasiswa cannot view
        Sanctum::actingAs($this->mhsOther);
        $this->getJson("/api/v1/proposals/{$this->proposal->id}/team")->assertStatus(403);
    }

    public function test_pengurus_can_update_team_member_role(): void
    {
        Sanctum::actingAs($this->pengurus);

        $team = ProjectTeam::create([
            'proposal_id' => $this->proposal->id,
            'user_id'     => $this->mhs1->id,
            'role_id'     => $this->roleFrontend->id,
            'joined_at'   => '2026-11-01',
        ]);

        $response = $this->putJson("/api/v1/proposals/{$this->proposal->id}/team/{$team->id}", [
            'role_id' => $this->roleBackend->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Role anggota tim berhasil diperbarui')
            ->assertJsonPath('data.role_id', $this->roleBackend->id);

        $this->assertDatabaseHas('project_teams', [
            'id'      => $team->id,
            'role_id' => $this->roleBackend->id,
        ]);
    }

    public function test_pengurus_can_remove_team_member_without_unfinished_tasks(): void
    {
        Sanctum::actingAs($this->pengurus);

        $team = ProjectTeam::create([
            'proposal_id' => $this->proposal->id,
            'user_id'     => $this->mhs1->id,
            'role_id'     => $this->roleFrontend->id,
            'joined_at'   => '2026-11-01',
        ]);

        $response = $this->deleteJson("/api/v1/proposals/{$this->proposal->id}/team/{$team->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Anggota tim berhasil dikeluarkan dari proyek.');

        $this->assertDatabaseMissing('project_teams', [
            'id' => $team->id,
        ]);
    }

    public function test_cannot_remove_team_member_with_unfinished_tasks(): void
    {
        Sanctum::actingAs($this->pengurus);

        $team = ProjectTeam::create([
            'proposal_id' => $this->proposal->id,
            'user_id'     => $this->mhs1->id,
            'role_id'     => $this->roleFrontend->id,
            'joined_at'   => '2026-11-01',
        ]);

        // Create an IN_PROGRESS task
        Task::create([
            'proposal_id'     => $this->proposal->id,
            'project_team_id' => $team->id,
            'title'           => 'Integrasi API Payment',
            'status'          => 'IN_PROGRESS',
        ]);

        $response = $this->deleteJson("/api/v1/proposals/{$this->proposal->id}/team/{$team->id}");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Tidak bisa mengeluarkan anggota yang memiliki task belum selesai.');

        $this->assertDatabaseHas('project_teams', [
            'id' => $team->id,
        ]);
    }
}
