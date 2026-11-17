<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ProjectRole;
use App\Models\ProjectTeam;
use App\Models\Proposal;
use App\Models\ProposalCategory;
use App\Models\ProposalStatus;
use App\Models\Task;
use App\Models\UatResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UatWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $client;
    private User $pengurus;
    private User $student;
    private Proposal $proposal;
    private ProjectTeam $team;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['klien', 'pengurus_tefa', 'dosen', 'mahasiswa'] as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $this->client = User::factory()->create();
        $this->client->assignRole('klien');
        $this->pengurus = User::factory()->create();
        $this->pengurus->assignRole('pengurus_tefa');
        $this->student = User::factory()->create();
        $this->student->assignRole('mahasiswa');

        $testingStatus = ProposalStatus::firstOrCreate(['name' => 'Testing']);
        ProposalStatus::firstOrCreate(['name' => 'Handover']);
        $category = ProposalCategory::firstOrCreate(['name' => 'Web']);
        $role = ProjectRole::firstOrCreate(['name' => 'Developer']);
        $this->proposal = Proposal::create([
            'client_id' => $this->client->id,
            'title' => 'UAT Workflow Project',
            'background' => str_repeat('Project details. ', 6),
            'category_id' => $category->id,
            'status_id' => $testingStatus->id,
            'proposal_code' => 'PRSL-2026-001',
            'pm_id' => User::factory()->create()->id,
        ]);
        $this->team = ProjectTeam::create([
            'proposal_id' => $this->proposal->id,
            'user_id' => $this->student->id,
            'role_id' => $role->id,
            'joined_at' => now()->toDateString(),
        ]);
    }

    public function test_client_submits_results_and_pengurus_turns_findings_into_student_tasks(): void
    {
        Sanctum::actingAs($this->client);
        $submitted = $this->postJson("/api/v1/proposals/{$this->proposal->id}/uat", [
            'client_notes' => 'Percobaan dari Klien.',
            'items' => [
                [
                    'feature_name' => 'Masuk',
                    'test_scenario' => 'Masuk dengan akun terdaftar',
                    'result' => 'PASSED',
                ],
                [
                    'feature_name' => 'Unduh laporan',
                    'test_scenario' => 'Unduh dalam format PDF',
                    'result' => 'FAILED',
                    'bug_description' => 'Tombol unduh tidak menghasilkan berkas.',
                ],
            ],
        ]);

        $submitted->assertCreated()
            ->assertJsonPath('data.overall_result', 'PARTIAL')
            ->assertJsonPath('data.review_status', 'PENDING_REVIEW')
            ->assertJsonPath('data.uat_items.1.result', 'FAILED');
        $uatId = (int) $submitted->json('data.id');
        $itemId = (int) $submitted->json('data.uat_items.1.id');

        Sanctum::actingAs($this->pengurus);
        $this->patchJson("/api/v1/proposals/{$this->proposal->id}/uat/{$uatId}/review", [
            'decision' => 'NEEDS_IMPROVEMENT',
            'review_notes' => 'Mohon lakukan tindak lanjut.',
            'corrections' => [[
                'item_id' => $itemId,
                'note' => 'Perbaiki proses pembuatan berkas PDF.',
                'assigned_student_id' => $this->student->id,
            ]],
        ])->assertOk()
            ->assertJsonPath('data.review_status', 'NEEDS_IMPROVEMENT')
            ->assertJsonPath('data.uat_items.1.tasks.0.assignee.id', $this->student->id);

        $task = Task::where('uat_item_id', $itemId)->firstOrFail();
        $this->assertSame($this->team->id, $task->project_team_id);
        $this->assertSame('TODO', $task->status);

        Sanctum::actingAs($this->student);
        $this->postJson("/api/v1/proposals/{$this->proposal->id}/tasks/{$task->id}/progress", [
            'achieved_summary' => 'Proses ekspor laporan PDF telah diperbaiki.',
            'progress_percentage' => 100,
            'status' => 'DONE',
        ])->assertOk()
            ->assertJsonPath('data.status', 'TESTING');

        Sanctum::actingAs($this->pengurus);
        $this->patchJson("/api/v1/proposals/{$this->proposal->id}/tasks/{$task->id}/review", [
            'pm_status' => 'APPROVED',
            'pm_notes' => 'Perbaikan sudah diverifikasi.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'DONE');

        $this->assertDatabaseHas('uat_results', [
            'id' => $uatId,
            'review_status' => 'READY_FOR_RETEST',
        ]);

        Sanctum::actingAs($this->client);
        $this->postJson("/api/v1/proposals/{$this->proposal->id}/uat", [
            'items' => [[
                'feature_name' => 'Unduh laporan',
                'test_scenario' => 'Unduh dalam format PDF',
                'result' => 'PASSED',
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.overall_result', 'PASSED');

        $this->assertDatabaseCount('uat_results', 2);
    }

    public function test_only_client_can_submit_uat_and_approval_waits_for_pengurus_review(): void
    {
        Sanctum::actingAs($this->pengurus);
        $this->postJson("/api/v1/proposals/{$this->proposal->id}/uat", [
            'items' => [['feature_name' => 'Masuk', 'result' => 'PASSED']],
        ])->assertForbidden();

        Sanctum::actingAs($this->client);
        $submitted = $this->postJson("/api/v1/proposals/{$this->proposal->id}/uat", [
            'items' => [['feature_name' => 'Masuk', 'result' => 'PASSED']],
        ])->assertCreated();
        $uatId = (int) $submitted->json('data.id');

        $this->patchJson("/api/v1/proposals/{$this->proposal->id}/uat/signoff", [
            'client_signoff_name' => $this->client->name,
        ])->assertStatus(422);

        Sanctum::actingAs($this->pengurus);
        $this->patchJson("/api/v1/proposals/{$this->proposal->id}/uat/{$uatId}/review", [
            'decision' => 'APPROVED',
            'review_notes' => 'Seluruh hasil sesuai.',
        ])->assertOk();

        Sanctum::actingAs($this->client);
        $this->patchJson("/api/v1/proposals/{$this->proposal->id}/uat/signoff", [
            'client_signoff_name' => $this->client->name,
        ])->assertOk()
            ->assertJsonPath('data.review_status', 'APPROVED');

        $this->assertSame(ProposalStatus::where('name', 'Handover')->value('id'), $this->proposal->fresh()->status_id);
    }

    public function test_retest_is_blocked_until_each_failed_feature_has_a_correction_task(): void
    {
        Sanctum::actingAs($this->client);
        $this->postJson("/api/v1/proposals/{$this->proposal->id}/uat", [
            'items' => [[
                'feature_name' => 'Masuk',
                'result' => 'FAILED',
                'bug_description' => 'Tidak dapat masuk.',
            ]],
        ])->assertCreated();
        $uat = UatResult::where('proposal_id', $this->proposal->id)->firstOrFail();
        $item = $uat->items()->firstOrFail();

        Sanctum::actingAs($this->pengurus);
        $this->patchJson("/api/v1/proposals/{$this->proposal->id}/uat/{$uat->id}/review", [
            'decision' => 'NEEDS_IMPROVEMENT',
            'corrections' => [[
                'item_id' => $item->id,
                'note' => 'Perbaiki autentikasi.',
                'assigned_student_id' => $this->student->id,
            ]],
        ])->assertOk();

        Sanctum::actingAs($this->client);
        $this->postJson("/api/v1/proposals/{$this->proposal->id}/uat", [
            'items' => [['feature_name' => 'Masuk', 'result' => 'PASSED']],
        ])->assertStatus(409);
    }

    public function test_pengurus_and_dosen_pm_can_set_testing_status(): void
    {
        $activeStatus = ProposalStatus::firstOrCreate(['name' => 'Active']);
        $testingStatus = ProposalStatus::where('name', 'Testing')->firstOrFail();

        $pmUser = User::factory()->create();
        $pmUser->assignRole('dosen');

        $otherDosen = User::factory()->create();
        $otherDosen->assignRole('dosen');

        $proposal = Proposal::create([
            'client_id' => $this->client->id,
            'title' => 'Proposal for Testing Status',
            'background' => str_repeat('Background test. ', 6),
            'category_id' => $this->proposal->category_id,
            'status_id' => $activeStatus->id,
            'proposal_code' => 'PRSL-2026-002',
            'pm_id' => $pmUser->id,
        ]);

        // Client cannot set testing
        Sanctum::actingAs($this->client);
        $this->patchJson("/api/v1/proposals/{$proposal->id}/status/testing")->assertForbidden();

        // Other dosen cannot set testing
        Sanctum::actingAs($otherDosen);
        $this->patchJson("/api/v1/proposals/{$proposal->id}/status/testing")->assertForbidden();

        // Dosen PM can set testing
        Sanctum::actingAs($pmUser);
        $this->patchJson("/api/v1/proposals/{$proposal->id}/status/testing")
            ->assertOk()
            ->assertJsonPath('data.status_id', $testingStatus->id);

        $this->assertSame($testingStatus->id, $proposal->fresh()->status_id);

        // Reset to active to test pengurus
        $proposal->update(['status_id' => $activeStatus->id]);

        // Pengurus TeFa can set testing
        Sanctum::actingAs($this->pengurus);
        $this->patchJson("/api/v1/proposals/{$proposal->id}/status/testing")
            ->assertOk()
            ->assertJsonPath('data.status_id', $testingStatus->id);

        $this->assertSame($testingStatus->id, $proposal->fresh()->status_id);
    }
}
