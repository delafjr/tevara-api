<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Proposal;
use App\Models\ProposalCategory;
use App\Models\ProposalStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContractTest extends TestCase
{
    use RefreshDatabase;

    private User $pengurus;
    private User $dosen;
    private User $mahasiswa;
    private User $klien;
    private Proposal $proposal;

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

        // Setup Users
        $this->pengurus = User::factory()->create(['name' => 'Pengurus TeFa']);
        $this->pengurus->assignRole('pengurus_tefa');

        $this->dosen = User::factory()->create(['name' => 'Dosen PM']);
        $this->dosen->assignRole('dosen');

        $this->mahasiswa = User::factory()->create(['name' => 'Mahasiswa Dev']);
        $this->mahasiswa->assignRole('mahasiswa');

        $this->klien = User::factory()->create(['name' => 'Klien PT']);
        $this->klien->assignRole('klien');

        // Setup Proposal
        $this->proposal = Proposal::create([
            'client_id'        => $this->klien->id,
            'title'            => 'Sistem Informasi E-Commerce',
            'background'       => str_repeat('Latar belakang sistem informasi e-commerce yang dibutuhkan. ', 3),
            'category_id'      => $cat->id,
            'desired_deadline' => now()->addDays(40)->toDateString(),
            'status_id'        => 5, // Approved
        ]);
    }

    public function test_pengurus_can_fill_contract_data(): void
    {
        Sanctum::actingAs($this->pengurus);

        $payload = [
            'scope_of_work'               => 'Pembangunan API dan dashboard web manajemen inventori.',
            'total_amount'                => 15000000,
            'payment_terms_description'   => 'Termin 1: 50%, Termin 2: 50%',
            'maintenance_warranty_months' => 6,
            'start_date'                  => '2026-11-01',
            'target_completion_date'      => '2027-01-31',
        ];

        $response = $this->patchJson("/api/v1/proposals/{$this->proposal->id}/contract", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Data SPK/kontrak berhasil disimpan')
            ->assertJsonPath('data.status_id', 7)
            ->assertJsonPath('data.total_amount', '15000000.00');

        $this->assertDatabaseHas('proposals', [
            'id'        => $this->proposal->id,
            'status_id' => 7, // Active
        ]);

        $fresh = $this->proposal->fresh();
        $this->assertNotNull($fresh->spk_number);
        $this->assertStringStartsWith('SPK/TEFA/', $fresh->spk_number);
        $this->assertNotNull($fresh->project_code);
        $this->assertStringStartsWith('PRJ-', $fresh->project_code);

        // Check maintenance warranty
        $this->assertDatabaseHas('project_maintenances', [
            'proposal_id'                 => $this->proposal->id,
            'maintenance_warranty_months' => 6,
        ]);

        // Check status histories
        $this->assertDatabaseHas('proposal_status_histories', [
            'proposal_id' => $this->proposal->id,
            'status_id'   => 7,
            'note'        => 'SPK/Kontrak diterbitkan',
        ]);
    }

    public function test_non_pengurus_cannot_fill_contract(): void
    {
        Sanctum::actingAs($this->mahasiswa);

        $response = $this->patchJson("/api/v1/proposals/{$this->proposal->id}/contract", [
            'scope_of_work'               => 'Test',
            'total_amount'                => 10000000,
            'payment_terms_description'   => 'Termin',
            'start_date'                  => '2026-11-01',
            'target_completion_date'      => '2027-01-31',
        ]);

        $response->assertStatus(403);
    }

    public function test_pengurus_can_sign_contract(): void
    {
        Sanctum::actingAs($this->pengurus);

        $response = $this->patchJson("/api/v1/proposals/{$this->proposal->id}/contract/sign");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Kontrak berhasil ditandatangani')
            ->assertJsonPath('data.is_signed', true);

        $this->assertDatabaseHas('proposals', [
            'id'        => $this->proposal->id,
            'is_signed' => true,
        ]);
    }

    public function test_pengurus_can_assign_pm_dosen(): void
    {
        Sanctum::actingAs($this->pengurus);

        $response = $this->patchJson("/api/v1/proposals/{$this->proposal->id}/contract/pm", [
            'pm_id' => $this->dosen->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Project Manager (Dosen) berhasil ditugaskan')
            ->assertJsonPath('data.pm_id', $this->dosen->id);

        $this->assertDatabaseHas('proposals', [
            'id'    => $this->proposal->id,
            'pm_id' => $this->dosen->id,
        ]);
    }

    public function test_cannot_assign_pm_if_user_is_not_dosen(): void
    {
        Sanctum::actingAs($this->pengurus);

        // mahasiswa is not a dosen
        $response = $this->patchJson("/api/v1/proposals/{$this->proposal->id}/contract/pm", [
            'pm_id' => $this->mahasiswa->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'User yang dipilih bukan merupakan Dosen.');
    }
}
