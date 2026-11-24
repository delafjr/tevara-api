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

    private User $pengurus;

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
        $this->pengurus = User::factory()->create();
        $this->pengurus->assignRole('pengurus_tefa');

        $category = ProposalCategory::firstOrCreate(['name' => 'Web']);
        $status = ProposalStatus::firstOrCreate(['name' => 'Active']);
        ProposalStatus::firstOrCreate(['name' => 'Completed']);
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

    public function test_signed_off_uat_appears_for_pengurus_and_bast_approval_completes_handover(): void
    {
        Storage::fake('public');
        $uat = UatResult::create([
            'proposal_id' => $this->proposal->id,
            'session_date' => now()->toDateString(),
            'overall_result' => 'PASSED',
            'client_signoff_name' => 'Klien Tevara',
            'signed_off_at' => now(),
        ]);
        Sanctum::actingAs($this->pengurus);

        $this->getJson('/api/v1/proposals/handover')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $this->proposal->id)
            ->assertJsonPath('0.latest_uat_result', 'PASSED')
            ->assertJsonPath('0.uat_signoff_name', 'Klien Tevara')
            ->assertJsonPath('0.bast_eligible', true);

        $prepared = $this->post("/api/v1/proposals/{$this->proposal->id}/handover/prepare", [
            'repository_url' => 'https://example.test/repository',
            'documentation_file' => UploadedFile::fake()->create('api-documentation.pdf', 20, 'application/pdf'),
            'user_manual_url' => 'https://example.test/user-manual',
            'handover_notes' => 'Seluruh produk diserahkan.',
            'handover_deliverables' => json_encode([[
                'name' => 'Aplikasi inventaris',
                'type' => 'digital',
                'quantity' => 1,
                'unit' => 'aplikasi',
                'condition' => 'not_applicable',
                'notes' => 'Akses tersedia melalui repository dan panduan pengguna.',
            ]]),
            'maintenance_warranty_months' => 3,
            'maintenance_notes' => 'Pemeliharaan sesuai SPK.',
        ]);

        $prepared->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.client_approved', false)
            ->assertJsonPath('data.maintenance.maintenance_warranty_months', 3)
            ->assertJsonPath('data.handover_deliverables.0.name', 'Aplikasi inventaris');
        $this->assertNull($prepared->json('data.handover_date'));
        Storage::disk('public')->assertExists($this->proposal->fresh()->documentation_url);

        Sanctum::actingAs($this->client);
        $response = $this->patchJson("/api/v1/proposals/{$this->proposal->id}/handover/approve", [
            'client_signoff_name' => 'PIC Klien',
            'client_approved' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.client_approved', true)
            ->assertJsonPath('data.status.name', 'Completed')
            ->assertJsonPath('data.maintenance.maintenance_warranty_months', 3)
            ->assertJsonMissingPath('data.risk_analysis');
        $this->assertSame(now()->toDateString(), substr((string) $response->json('data.handover_date'), 0, 10));
        $this->assertSame(now()->addDay()->toDateString(), substr((string) $response->json('data.maintenance.maintenance_start_date'), 0, 10));

        $this->assertDatabaseHas('proposals', [
            'id' => $this->proposal->id,
            'bast_number' => 'BAST/TEFA/'.now()->format('Y').'/001',
            'client_approved' => true,
            'status_id' => ProposalStatus::where('name', 'Completed')->value('id'),
            'has_warranty' => true,
            'warranty_months' => 3,
            'warranty_start_date' => now()->addDay()->toDateString().' 00:00:00',
            'warranty_end_date' => now()->addDay()->addMonthsNoOverflow(3)->toDateString().' 00:00:00',
        ]);
        $this->get("/api/v1/proposals/{$this->proposal->id}/handover/documentation")
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_tefa_managed_handover_requires_app_url_and_allows_login_credentials_without_repository_url(): void
    {
        Storage::fake('public');
        $this->proposal->update(['delivery_type' => 'tefa_managed']);
        UatResult::create([
            'proposal_id' => $this->proposal->id,
            'session_date' => now()->toDateString(),
            'overall_result' => 'PASSED',
            'client_signoff_name' => 'Klien Tevara',
            'signed_off_at' => now(),
        ]);

        // Prepare without repository_url but with app_url and access credentials
        Sanctum::actingAs($this->pengurus);
        $file = UploadedFile::fake()->create('dokumen-sistem.pdf', 100, 'application/pdf');
        $prepareRes = $this->postJson("/api/v1/proposals/{$this->proposal->id}/handover/prepare", [
            'app_url' => 'https://aplikasi-klien.tefa.polines.ac.id',
            'access_email' => 'admin@klien.com',
            'access_password' => 'Rahasia123!',
            'documentation_file' => $file,
            'handover_deliverables' => [
                [
                    'name' => 'Akun Administrator & Akses Web Sistem',
                    'type' => 'digital',
                    'quantity' => 1,
                    'unit' => 'sistem',
                    'condition' => 'good',
                    'notes' => 'Akses login dashboard operasional TeFa-managed',
                ],
            ],
            'has_warranty' => true,
            'warranty_months' => 6,
        ]);
        $prepareRes->assertOk();

        $this->assertDatabaseHas('proposals', [
            'id' => $this->proposal->id,
            'app_url' => 'https://aplikasi-klien.tefa.polines.ac.id',
            'access_email' => 'admin@klien.com',
            'access_password' => 'Rahasia123!',
            'repository_url' => null,
        ]);

        // Client approves
        Sanctum::actingAs($this->client);
        $approveRes = $this->patchJson("/api/v1/proposals/{$this->proposal->id}/handover/approve", [
            'client_signoff_name' => 'Budi Santoso (Direktur)',
            'client_approved' => true,
        ]);
        $approveRes->assertOk();

        $this->assertDatabaseHas('proposals', [
            'id' => $this->proposal->id,
            'client_approved' => true,
            'status_id' => ProposalStatus::where('name', 'Completed')->value('id'),
        ]);
    }

    public function test_pengurus_cannot_prepare_bast_until_uat_is_signed_and_passed(): void
    {
        UatResult::create([
            'proposal_id' => $this->proposal->id,
            'session_date' => now()->toDateString(),
            'overall_result' => 'PASSED',
        ]);
        Sanctum::actingAs($this->pengurus);

        $response = $this->postJson("/api/v1/proposals/{$this->proposal->id}/handover/prepare", [
            'repository_url' => 'https://example.test/repository',
            'documentation_file' => UploadedFile::fake()->create('api-documentation.pdf', 20, 'application/pdf'),
            'handover_deliverables' => [[
                'name' => 'Aplikasi inventaris',
                'type' => 'digital',
                'quantity' => 1,
                'unit' => 'aplikasi',
                'condition' => 'not_applicable',
            ]],
            'maintenance_warranty_months' => 3,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('uat');
        $this->assertDatabaseHas('proposals', [
            'id' => $this->proposal->id,
            'bast_number' => null,
        ]);
    }

    public function test_client_cannot_approve_before_pengurus_prepares_bast(): void
    {
        UatResult::create([
            'proposal_id' => $this->proposal->id,
            'session_date' => now()->toDateString(),
            'overall_result' => 'PASSED',
            'client_signoff_name' => 'Klien Tevara',
            'signed_off_at' => now(),
        ]);
        Sanctum::actingAs($this->client);

        $this->patchJson("/api/v1/proposals/{$this->proposal->id}/handover/approve", [
            'client_signoff_name' => 'PIC Klien',
            'client_approved' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('handover');
    }

    public function test_client_cannot_approve_handover_for_another_client_project(): void
    {
        Storage::fake('public');
        UatResult::create([
            'proposal_id' => $this->proposal->id,
            'session_date' => now()->toDateString(),
            'overall_result' => 'PASSED',
            'client_signoff_name' => 'Klien Tevara',
            'signed_off_at' => now(),
        ]);
        Sanctum::actingAs($this->pengurus);
        $this->post("/api/v1/proposals/{$this->proposal->id}/handover/prepare", [
            'repository_url' => 'https://example.test/repository',
            'documentation_file' => UploadedFile::fake()->create('api-documentation.pdf', 20, 'application/pdf'),
            'handover_deliverables' => [[
                'name' => 'Aplikasi inventaris',
                'type' => 'digital',
                'quantity' => 1,
                'unit' => 'aplikasi',
                'condition' => 'not_applicable',
            ]],
            'maintenance_warranty_months' => 3,
        ])->assertOk();

        $otherClient = User::factory()->create();
        $otherClient->assignRole('klien');
        Sanctum::actingAs($otherClient);

        $response = $this->patchJson("/api/v1/proposals/{$this->proposal->id}/handover/approve", [
            'client_signoff_name' => 'PIC lain',
            'client_approved' => true,
        ]);

        $response->assertForbidden();
    }

    public function test_client_project_list_includes_latest_uat_and_bast_eligibility(): void
    {
        $this->proposal->forceFill([
            'handover_deliverables' => [[
                'name' => 'Aplikasi inventaris',
                'type' => 'digital',
                'quantity' => 1,
                'unit' => 'aplikasi',
                'condition' => 'not_applicable',
            ]],
        ])->save();
        UatResult::create([
            'proposal_id' => $this->proposal->id,
            'session_date' => now()->toDateString(),
            'overall_result' => 'PASSED',
            'client_signoff_name' => 'Klien Tevara',
            'signed_off_at' => now(),
        ]);
        Sanctum::actingAs($this->client);

        $this->getJson('/api/v1/proposals/mine')
            ->assertOk()
            ->assertJsonPath('0.project_code', 'PRJ-2026/001')
            ->assertJsonPath('0.latest_uat_result', 'PASSED')
            ->assertJsonPath('0.bast_eligible', true)
            ->assertJsonPath('0.handover_deliverables.0.name', 'Aplikasi inventaris');
    }

    public function test_client_cannot_approve_bast_without_handover_deliverables(): void
    {
        Storage::fake('public');
        UatResult::create([
            'proposal_id' => $this->proposal->id,
            'session_date' => now()->toDateString(),
            'overall_result' => 'PASSED',
            'client_signoff_name' => 'Klien Tevara',
            'signed_off_at' => now(),
        ]);
        Sanctum::actingAs($this->pengurus);
        $this->post("/api/v1/proposals/{$this->proposal->id}/handover/prepare", [
            'repository_url' => 'https://example.test/repository',
            'documentation_file' => UploadedFile::fake()->create('api-documentation.pdf', 20, 'application/pdf'),
            'handover_deliverables' => [[
                'name' => 'Aplikasi inventaris',
                'type' => 'digital',
                'quantity' => 1,
                'unit' => 'aplikasi',
                'condition' => 'not_applicable',
            ]],
            'maintenance_warranty_months' => 3,
        ])->assertOk();

        $this->proposal->forceFill(['handover_deliverables' => []])->save();
        Sanctum::actingAs($this->client);
        $this->patchJson("/api/v1/proposals/{$this->proposal->id}/handover/approve", [
            'client_signoff_name' => 'PIC Klien',
            'client_approved' => true,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('handover');
    }

    public function test_endpoint_prepare_menolak_input_nomor_bast_dan_tanggal_serah_terima(): void
    {
        Storage::fake('public');
        UatResult::create([
            'proposal_id' => $this->proposal->id,
            'session_date' => now()->toDateString(),
            'overall_result' => 'PASSED',
            'client_signoff_name' => 'Klien Tevara',
            'signed_off_at' => now(),
        ]);
        Sanctum::actingAs($this->pengurus);

        // Uji coba memanipulasi nomor_bast
        $response = $this->postJson("/api/v1/proposals/{$this->proposal->id}/handover/prepare", [
            'nomor_bast' => 'BAST/MANIPULASI/2026/999',
            'repository_url' => 'https://example.test/repository',
            'handover_deliverables' => [[
                'name' => 'Aplikasi inventaris',
                'type' => 'digital',
                'quantity' => 1,
                'unit' => 'aplikasi',
                'condition' => 'not_applicable',
            ]],
            'maintenance_warranty_months' => 3,
        ]);
        $response->assertStatus(422);

        // Uji coba memanipulasi tanggal_serah_terima
        $response2 = $this->postJson("/api/v1/proposals/{$this->proposal->id}/handover/prepare", [
            'tanggal_serah_terima' => '2026-01-01',
            'repository_url' => 'https://example.test/repository',
            'handover_deliverables' => [[
                'name' => 'Aplikasi inventaris',
                'type' => 'digital',
                'quantity' => 1,
                'unit' => 'aplikasi',
                'condition' => 'not_applicable',
            ]],
            'maintenance_warranty_months' => 3,
        ]);
        $response2->assertStatus(422);
    }

    public function test_endpoint_approve_menolak_input_nomor_bast_dan_tanggal_serah_terima(): void
    {
        Storage::fake('public');
        UatResult::create([
            'proposal_id' => $this->proposal->id,
            'session_date' => now()->toDateString(),
            'overall_result' => 'PASSED',
            'client_signoff_name' => 'Klien Tevara',
            'signed_off_at' => now(),
        ]);
        Sanctum::actingAs($this->pengurus);
        $this->post("/api/v1/proposals/{$this->proposal->id}/handover/prepare", [
            'repository_url' => 'https://example.test/repository',
            'documentation_file' => UploadedFile::fake()->create('api-documentation.pdf', 20, 'application/pdf'),
            'handover_deliverables' => [[
                'name' => 'Aplikasi inventaris',
                'type' => 'digital',
                'quantity' => 1,
                'unit' => 'aplikasi',
                'condition' => 'not_applicable',
            ]],
            'maintenance_warranty_months' => 3,
        ])->assertOk();

        Sanctum::actingAs($this->client);
        $response = $this->patchJson("/api/v1/proposals/{$this->proposal->id}/handover/approve", [
            'client_signoff_name' => 'PIC Klien',
            'client_approved' => true,
            'nomor_bast' => 'BAST/HACK/001',
        ]);
        $response->assertStatus(422);

        $response2 = $this->patchJson("/api/v1/proposals/{$this->proposal->id}/handover/approve", [
            'client_signoff_name' => 'PIC Klien',
            'client_approved' => true,
            'tanggal_serah_terima' => '2026-01-01',
        ]);
        $response2->assertStatus(422);
    }

    public function test_validasi_file_dokumentasi_api_membatasi_ekstensi_dan_ukuran_maksimal(): void
    {
        Storage::fake('public');
        UatResult::create([
            'proposal_id' => $this->proposal->id,
            'session_date' => now()->toDateString(),
            'overall_result' => 'PASSED',
            'client_signoff_name' => 'Klien Tevara',
            'signed_off_at' => now(),
        ]);
        Sanctum::actingAs($this->pengurus);

        // Ekstensi tidak aman (.php / .exe)
        $invalidExtension = $this->post("/api/v1/proposals/{$this->proposal->id}/handover/prepare", [
            'repository_url' => 'https://example.test/repository',
            'documentation_file' => UploadedFile::fake()->create('exploit.php', 20, 'text/x-php'),
            'handover_deliverables' => json_encode([[
                'name' => 'Aplikasi',
                'type' => 'digital',
                'quantity' => 1,
                'unit' => 'aplikasi',
                'condition' => 'not_applicable',
            ]]),
            'maintenance_warranty_months' => 3,
        ]);
        $invalidExtension->assertStatus(422)->assertJsonValidationErrors('documentation_file');

        // Ukuran melebihi 10MB (10241 KB)
        $oversizedFile = $this->post("/api/v1/proposals/{$this->proposal->id}/handover/prepare", [
            'repository_url' => 'https://example.test/repository',
            'documentation_file' => UploadedFile::fake()->create('large-docs.zip', 11000, 'application/zip'),
            'handover_deliverables' => json_encode([[
                'name' => 'Aplikasi',
                'type' => 'digital',
                'quantity' => 1,
                'unit' => 'aplikasi',
                'condition' => 'not_applicable',
            ]]),
            'maintenance_warranty_months' => 3,
        ]);
        $oversizedFile->assertStatus(422)->assertJsonValidationErrors('documentation_file');

        // Ekstensi aman .zip diperbolehkan
        $validZip = $this->post("/api/v1/proposals/{$this->proposal->id}/handover/prepare", [
            'repository_url' => 'https://example.test/repository',
            'documentation_file' => UploadedFile::fake()->create('docs.zip', 1000, 'application/zip'),
            'handover_deliverables' => json_encode([[
                'name' => 'Aplikasi',
                'type' => 'digital',
                'quantity' => 1,
                'unit' => 'aplikasi',
                'condition' => 'not_applicable',
            ]]),
            'maintenance_warranty_months' => 3,
        ]);
        $validZip->assertOk();

        // Ekstensi aman .docx diperbolehkan
        $validDocx = $this->post("/api/v1/proposals/{$this->proposal->id}/handover/prepare", [
            'repository_url' => 'https://example.test/repository',
            'documentation_file' => UploadedFile::fake()->create('docs.docx', 1000, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            'handover_deliverables' => json_encode([[
                'name' => 'Aplikasi',
                'type' => 'digital',
                'quantity' => 1,
                'unit' => 'aplikasi',
                'condition' => 'not_applicable',
            ]]),
            'maintenance_warranty_months' => 6,
        ]);
        $validDocx->assertOk();
    }

    public function test_kalkulasi_tanggal_mulai_dan_akhir_garansi_sesuai_pilihan_durasi(): void
    {
        Storage::fake('public');
        UatResult::create([
            'proposal_id' => $this->proposal->id,
            'session_date' => now()->toDateString(),
            'overall_result' => 'PASSED',
            'client_signoff_name' => 'Klien Tevara',
            'signed_off_at' => now(),
        ]);
        Sanctum::actingAs($this->pengurus);
        $this->post("/api/v1/proposals/{$this->proposal->id}/handover/prepare", [
            'repository_url' => 'https://example.test/repository',
            'documentation_file' => UploadedFile::fake()->create('api-documentation.pdf', 20, 'application/pdf'),
            'handover_deliverables' => [[
                'name' => 'Aplikasi inventaris',
                'type' => 'digital',
                'quantity' => 1,
                'unit' => 'aplikasi',
                'condition' => 'not_applicable',
            ]],
            'maintenance_warranty_months' => 3,
        ])->assertOk();

        Sanctum::actingAs($this->client);
        // Klien memilih durasi garansi 6 bulan saat approve
        $response = $this->patchJson("/api/v1/proposals/{$this->proposal->id}/handover/approve", [
            'client_signoff_name' => 'PIC Klien',
            'client_approved' => true,
            'maintenance_warranty_months' => 6,
        ]);

        $response->assertOk();
        $expectedStartDate = now()->addDay()->toDateString();
        $expectedEndDate = now()->addDay()->addMonthsNoOverflow(6)->toDateString();

        $this->assertSame($expectedStartDate, substr((string) $response->json('data.maintenance.maintenance_start_date'), 0, 10));
        $this->assertSame($expectedEndDate, substr((string) $response->json('data.maintenance.maintenance_end_date'), 0, 10));
        $this->assertSame(6, (int) $response->json('data.maintenance.maintenance_warranty_months'));
    }
}
