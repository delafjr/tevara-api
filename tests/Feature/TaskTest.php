<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ProjectRole;
use App\Models\ProjectTeam;
use App\Models\Proposal;
use App\Models\ProposalCategory;
use App\Models\ProposalStatus;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    private User $pengurus;
    private User $dosenPm;
    private User $dosenOther;
    private User $mhs1;
    private User $mhs2;
    private User $mhsOther;
    private Proposal $proposal;
    private ProjectTeam $team1;
    private ProjectTeam $team2;
    private TaskCategory $catFrontend;
    private TaskCategory $catBackend;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

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

        // Setup Categories
        $cat = ProposalCategory::firstOrCreate(['name' => 'Web']);
        $this->catFrontend = TaskCategory::firstOrCreate(['name' => 'Frontend']);
        $this->catBackend  = TaskCategory::firstOrCreate(['name' => 'Backend']);

        // Setup Project Roles
        $roleDev = ProjectRole::firstOrCreate(['name' => 'Developer']);

        // Setup Users
        $this->pengurus = User::factory()->create(['name' => 'Pengurus TeFa']);
        $this->pengurus->assignRole('pengurus_tefa');

        $this->dosenPm = User::factory()->create(['name' => 'Dosen PM']);
        $this->dosenPm->assignRole('dosen');

        $this->dosenOther = User::factory()->create(['name' => 'Dosen Lain']);
        $this->dosenOther->assignRole('dosen');

        $this->mhs1 = User::factory()->create(['name' => 'Mahasiswa 1']);
        $this->mhs1->assignRole('mahasiswa');

        $this->mhs2 = User::factory()->create(['name' => 'Mahasiswa 2']);
        $this->mhs2->assignRole('mahasiswa');

        $this->mhsOther = User::factory()->create(['name' => 'Mahasiswa Luar']);
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

        // Add students to team
        $this->team1 = ProjectTeam::create([
            'proposal_id' => $this->proposal->id,
            'user_id'     => $this->mhs1->id,
            'role_id'     => $roleDev->id,
            'joined_at'   => '2026-11-01',
        ]);

        $this->team2 = ProjectTeam::create([
            'proposal_id' => $this->proposal->id,
            'user_id'     => $this->mhs2->id,
            'role_id'     => $roleDev->id,
            'joined_at'   => '2026-11-01',
        ]);
    }

    public function test_pm_can_create_task(): void
    {
        Sanctum::actingAs($this->dosenPm);

        $payload = [
            'title'               => 'Implementasi Login API',
            'description'         => 'Buat endpoint autentikasi Sanctum',
            'category_id'         => $this->catBackend->id,
            'assigned_student_id' => $this->mhs1->id,
            'priority'            => 'HIGH',
            'due_date'            => '2026-11-15',
        ];

        $response = $this->postJson("/api/v1/proposals/{$this->proposal->id}/tasks", $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Implementasi Login API')
            ->assertJsonPath('data.status', 'TODO')
            ->assertJsonPath('data.progress_percentage', 0)
            ->assertJsonPath('data.pm_status', 'PENDING')
            ->assertJsonPath('data.assignee.id', $this->mhs1->id);

        $this->assertDatabaseHas('tasks', [
            'proposal_id'     => $this->proposal->id,
            'project_team_id' => $this->team1->id,
            'title'           => 'Implementasi Login API',
            'status'          => 'TODO',
            'priority'        => 'HIGH',
        ]);
    }

    public function test_cannot_assign_task_to_student_not_in_team(): void
    {
        Sanctum::actingAs($this->dosenPm);

        $payload = [
            'title'               => 'Task Luar',
            'assigned_student_id' => $this->mhsOther->id,
        ];

        $response = $this->postJson("/api/v1/proposals/{$this->proposal->id}/tasks", $payload);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Mahasiswa yang ditunjuk bukan anggota tim proyek ini.');
    }

    public function test_non_pm_dosen_cannot_create_task(): void
    {
        Sanctum::actingAs($this->dosenOther);

        $payload = [
            'title' => 'Task Ilegal',
        ];

        $response = $this->postJson("/api/v1/proposals/{$this->proposal->id}/tasks", $payload);
        $response->assertStatus(403);
    }

    public function test_mahasiswa_cannot_create_task(): void
    {
        Sanctum::actingAs($this->mhs1);

        $payload = [
            'title' => 'Task Buatan Mahasiswa',
        ];

        $response = $this->postJson("/api/v1/proposals/{$this->proposal->id}/tasks", $payload);
        $response->assertStatus(403);
    }

    public function test_mahasiswa_list_auto_scoped_to_own_tasks(): void
    {
        // Buat task 1 untuk mhs1
        Task::create([
            'proposal_id'         => $this->proposal->id,
            'project_team_id'     => $this->team1->id,
            'title'               => 'Task Mhs 1',
            'status'              => 'TODO',
            'progress_percentage' => 0,
        ]);

        // Buat task 2 untuk mhs2
        Task::create([
            'proposal_id'         => $this->proposal->id,
            'project_team_id'     => $this->team2->id,
            'title'               => 'Task Mhs 2',
            'status'              => 'TODO',
            'progress_percentage' => 0,
        ]);

        // Acting as mhs1
        Sanctum::actingAs($this->mhs1);

        $response = $this->getJson("/api/v1/proposals/{$this->proposal->id}/tasks");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Task Mhs 1');

        // Acting as PM: should see both
        Sanctum::actingAs($this->dosenPm);
        $responsePm = $this->getJson("/api/v1/proposals/{$this->proposal->id}/tasks");
        $responsePm->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_mahasiswa_cannot_view_another_students_task(): void
    {
        $task2 = Task::create([
            'proposal_id'         => $this->proposal->id,
            'project_team_id'     => $this->team2->id,
            'title'               => 'Task Mhs 2',
            'status'              => 'TODO',
            'progress_percentage' => 0,
        ]);

        Sanctum::actingAs($this->mhs1);

        $response = $this->getJson("/api/v1/proposals/{$this->proposal->id}/tasks/{$task2->id}");
        $response->assertStatus(403);
    }

    public function test_mahasiswa_can_submit_progress_report(): void
    {
        $task = Task::create([
            'proposal_id'         => $this->proposal->id,
            'project_team_id'     => $this->team1->id,
            'title'               => 'Slicing UI Dashboard',
            'status'              => 'TODO',
            'progress_percentage' => 0,
            'pm_status'           => 'APPROVED',
        ]);

        Sanctum::actingAs($this->mhs1);

        $file = UploadedFile::fake()->create('report.pdf', 500, 'application/pdf');

        $payload = [
            'achieved_summary'    => 'Menyelesaikan slicing komponen header dan sidebar',
            'blockers_summary'    => 'Belum ada blocker',
            'progress_percentage' => 60,
            'status'              => 'IN_PROGRESS',
            'report_attachment'   => $file,
        ];

        $response = $this->postJson("/api/v1/proposals/{$this->proposal->id}/tasks/{$task->id}/progress", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'IN_PROGRESS')
            ->assertJsonPath('data.progress_percentage', 60)
            ->assertJsonPath('data.pm_status', 'PENDING'); // Reset ke PENDING

        $this->assertDatabaseHas('tasks', [
            'id'                  => $task->id,
            'status'              => 'IN_PROGRESS',
            'progress_percentage' => 60,
            'pm_status'           => 'PENDING',
        ]);
    }

    public function test_dosen_pm_can_review_progress_report(): void
    {
        $task = Task::create([
            'proposal_id'         => $this->proposal->id,
            'project_team_id'     => $this->team1->id,
            'title'               => 'Slicing UI Dashboard',
            'status'              => 'IN_PROGRESS',
            'progress_percentage' => 60,
            'pm_status'           => 'PENDING',
        ]);

        Sanctum::actingAs($this->dosenPm);

        $payload = [
            'pm_status' => 'APPROVED',
            'pm_notes'  => 'Pekerjaan rapi sesuai mockup, lanjutkan ke tahap integrasi.',
        ];

        $response = $this->patchJson("/api/v1/proposals/{$this->proposal->id}/tasks/{$task->id}/review", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.pm_status', 'APPROVED')
            ->assertJsonPath('data.pm_notes', 'Pekerjaan rapi sesuai mockup, lanjutkan ke tahap integrasi.');

        $task->refresh();
        $this->assertEquals('APPROVED', $task->pm_status);
        $this->assertNotNull($task->pm_reviewed_at);
    }

    public function test_pm_can_update_task_metadata(): void
    {
        $task = Task::create([
            'proposal_id'         => $this->proposal->id,
            'project_team_id'     => $this->team1->id,
            'title'               => 'Judul Lama',
            'priority'            => 'LOW',
            'status'              => 'TODO',
            'progress_percentage' => 0,
        ]);

        Sanctum::actingAs($this->dosenPm);

        $payload = [
            'title'               => 'Judul Baru yang Diperbarui',
            'priority'            => 'CRITICAL',
            'assigned_student_id' => $this->mhs2->id,
        ];

        $response = $this->putJson("/api/v1/proposals/{$this->proposal->id}/tasks/{$task->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Judul Baru yang Diperbarui')
            ->assertJsonPath('data.priority', 'CRITICAL')
            ->assertJsonPath('data.assignee.id', $this->mhs2->id);

        $this->assertDatabaseHas('tasks', [
            'id'              => $task->id,
            'title'           => 'Judul Baru yang Diperbarui',
            'priority'        => 'CRITICAL',
            'project_team_id' => $this->team2->id,
        ]);
    }

    public function test_can_delete_task_with_zero_progress(): void
    {
        $task = Task::create([
            'proposal_id'         => $this->proposal->id,
            'project_team_id'     => $this->team1->id,
            'title'               => 'Task yang akan dihapus',
            'status'              => 'TODO',
            'progress_percentage' => 0,
        ]);

        Sanctum::actingAs($this->dosenPm);

        $response = $this->deleteJson("/api/v1/proposals/{$this->proposal->id}/tasks/{$task->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_cannot_delete_task_with_progress_percentage_greater_than_zero(): void
    {
        $task = Task::create([
            'proposal_id'         => $this->proposal->id,
            'project_team_id'     => $this->team1->id,
            'title'               => 'Task Sedang Berjalan',
            'status'              => 'IN_PROGRESS',
            'progress_percentage' => 30,
        ]);

        Sanctum::actingAs($this->dosenPm);

        $response = $this->deleteJson("/api/v1/proposals/{$this->proposal->id}/tasks/{$task->id}");

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_task_summary_endpoint(): void
    {
        Task::create([
            'proposal_id'         => $this->proposal->id,
            'project_team_id'     => $this->team1->id,
            'title'               => 'Task 1',
            'status'              => 'DONE',
            'progress_percentage' => 100,
            'pm_status'           => 'APPROVED',
        ]);

        Task::create([
            'proposal_id'         => $this->proposal->id,
            'project_team_id'     => $this->team2->id,
            'title'               => 'Task 2',
            'status'              => 'IN_PROGRESS',
            'progress_percentage' => 50,
            'pm_status'           => 'PENDING',
        ]);

        Sanctum::actingAs($this->dosenPm);

        $response = $this->getJson("/api/v1/proposals/{$this->proposal->id}/tasks/summary");

        $response->assertStatus(200)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.done', 1)
            ->assertJsonPath('data.in_progress', 1)
            ->assertJsonPath('data.approved', 1)
            ->assertJsonPath('data.pending_review', 1)
            ->assertJsonPath('data.overall_progress', 75);
    }
}
