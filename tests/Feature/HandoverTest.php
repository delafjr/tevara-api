<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ProjectRole;
use App\Models\ProjectTeam;
use App\Models\Proposal;
use App\Models\ProposalCategory;
use App\Models\ProposalStatus;
use App\Models\UatResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HandoverTest extends TestCase
{
    use RefreshDatabase;

    private User $client;
    private Proposal $proposal;
    private ProjectTeam $team;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['klien', 'pengurus_tefa', 'dosen', 'mahasiswa', 'jurusan'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->client = User::factory()->create();
        $this->client->assignRole('klien');

        $category = ProposalCategory::firstOrCreate(['name' => 'Web']);
        $status = ProposalStatus::firstOrCreate(['name' => 'Active']);
        ProposalStatus::firstOrCreate(['name' => 'Handover']);
        $projectRole = ProjectRole::firstOrCreate(['name' => 'Developer']);

        $this->proposal = Proposal::create([
            'client_id' => $this->client->id,
            'title' => 'Project handover test',
            'background' => str_repeat('Project background. ', 6),
            'risk_analysis' => 'Internal risk notes must remain private.',
            'project_code' => 'PRJ-2026/001',
            'category_id' => $category->id,
            'status_id' => $status->id,
        ]);

        $this->team = ProjectTeam::create([
            'proposal_id' => $this->proposal->id,
            'user_id' => $this->client->id,
            'role_id' => $projectRole->id,
            'joined_at' => now()->toDateString(),
        ]);
    }

    public function test_client_can_submit_handover_after_latest_uat_passes(): void
    {
        Storage::fake('public');
        UatResult::create([
            'project_team_id' => $this->team->id,
            'session_date' => now()->toDateString(),
            'overall_result' => 'PASSED',
        ]);
        Sanctum::actingAs($this->client);

        $response = $this->post("/api/v1/proposals/{$this->proposal->id}/handover", [
            'repository_url' => 'https://example.test/repository',
            'documentation_file' => UploadedFile::fake()->create('api-documentation.pdf', 20, 'application/pdf'),
            'handover_notes' => 'Seluruh produk diserahkan.',
            'client_signoff_name' => 'Klien Tevara',
            'client_approved' => true,
            'maintenance_warranty_months' => 3,
            'maintenance_notes' => 'Pemeliharaan sesuai SPK.',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.client_approved', true)
            ->assertJsonPath('data.status.name', 'Handover')
            ->assertJsonPath('data.maintenance.maintenance_warranty_months', 3)
            ->assertJsonMissingPath('data.risk_analysis');
        $this->assertSame(now()->toDateString(), substr((string) $response->json('data.handover_date'), 0, 10));
        $this->assertSame(now()->addDay()->toDateString(), substr((string) $response->json('data.maintenance.maintenance_start_date'), 0, 10));

        $this->assertDatabaseHas('proposals', [
            'id' => $this->proposal->id,
            'bast_number' => 'BAST/TEFA/' . now()->format('Y') . '/001',
            'client_approved' => true,
        ]);
        $this->assertDatabaseHas('project_maintenances', [
            'proposal_id' => $this->proposal->id,
            'maintenance_warranty_months' => 3,
            'maintenance_start_date' => now()->addDay()->toDateString() . ' 00:00:00',
            'maintenance_end_date' => now()->addDay()->addMonthsNoOverflow(3)->toDateString() . ' 00:00:00',
        ]);
        Storage::disk('public')->assertExists($this->proposal->fresh()->documentation_url);
    }

    public function test_client_cannot_submit_handover_until_uat_passes(): void
    {
        UatResult::create([
            'project_team_id' => $this->team->id,
            'session_date' => now()->toDateString(),
            'overall_result' => 'PARTIAL',
        ]);
        Sanctum::actingAs($this->client);

        $response = $this->postJson("/api/v1/proposals/{$this->proposal->id}/handover", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('uat');
        $this->assertDatabaseMissing('proposals', [
            'id' => $this->proposal->id,
            'client_approved' => true,
        ]);
    }

    public function test_client_cannot_submit_handover_for_another_client_project(): void
    {
        $otherClient = User::factory()->create();
        $otherClient->assignRole('klien');
        Sanctum::actingAs($otherClient);

        $response = $this->postJson("/api/v1/proposals/{$this->proposal->id}/handover", []);

        $response->assertForbidden();
    }

    public function test_client_project_list_includes_latest_uat_and_bast_eligibility(): void
    {
        UatResult::create([
            'project_team_id' => $this->team->id,
            'session_date' => now()->toDateString(),
            'overall_result' => 'PASSED',
        ]);
        Sanctum::actingAs($this->client);

        $this->getJson('/api/v1/proposals/mine')
            ->assertOk()
            ->assertJsonPath('0.project_code', 'PRJ-2026/001')
            ->assertJsonPath('0.latest_uat_result', 'PASSED')
            ->assertJsonPath('0.bast_eligible', true);
    }
}
