<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ClientPic;
use App\Models\ClientProfile;
use App\Models\Notification;
use App\Models\ProposalCategory;
use App\Models\ProposalStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProposalCreationNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $klien;
    private ProposalCategory $category;

    /** @var array<User> */
    private array $pengurusList = [];

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'pengurus_tefa', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'klien', 'guard_name' => 'web']);

        $statuses = [
            1 => 'Draft',
            2 => 'Submitted',
            3 => 'Under Review',
            4 => 'Revision Required',
            5 => 'Approved',
            6 => 'Rejected',
            7 => 'Active',
            8 => 'Testing',
            9 => 'Handover',
            10 => 'Completed',
        ];
        foreach ($statuses as $id => $name) {
            ProposalStatus::firstOrCreate(['id' => $id], ['name' => $name]);
        }

        $this->category = ProposalCategory::firstOrCreate(['name' => 'Web']);

        // Buat 3 pengurus TeFa berbeda
        $this->pengurusList[] = User::factory()->create(['name' => 'Admin Pengurus 1', 'email' => 'pengurus1@tefa.id']);
        $this->pengurusList[] = User::factory()->create(['name' => 'Admin Pengurus 2', 'email' => 'pengurus2@tefa.id']);
        $this->pengurusList[] = User::factory()->create(['name' => 'Admin Pengurus 3', 'email' => 'pengurus3@tefa.id']);

        foreach ($this->pengurusList as $p) {
            $p->assignRole('pengurus_tefa');
        }

        // Klien perusahaan & PIC
        $owner = User::factory()->create(['name' => 'PT Solusi Bangsa', 'email' => 'contact@solusibangsa.id']);
        $owner->assignRole('klien');
        $profile = ClientProfile::create([
            'user_id'     => $owner->id,
            'address'     => 'Jl. Sudirman No. 1',
            'client_type' => 'EXTERNAL',
        ]);

        $this->klien = User::factory()->create(['name' => 'Ahmad PIC', 'email' => 'ahmad@solusibangsa.id']);
        $this->klien->assignRole('klien');
        ClientPic::create([
            'client_profile_id' => $profile->id,
            'user_id'           => $this->klien->id,
            'position'          => 'Product Manager',
        ]);
    }

    public function test_when_client_submits_proposal_all_pengurus_tefa_receive_notifications(): void
    {
        Sanctum::actingAs($this->klien);

        $payload = [
            'client_id'        => $this->klien->id,
            'title'            => 'Sistem Informasi Manajemen Aset',
            'background'       => 'Kebutuhan digitalisasi pencatatan aset kantor yang terintegrasi dan akurat untuk seluruh cabang perusahaan.',
            'target_audience'  => 'Tim operasional dan manajemen aset perusahaan',
            'category_id'      => $this->category->id,
            'desired_deadline' => now()->addDays(90)->toDateString(),
            'estimated_budget' => 50000000,
            'modules'          => [
                [
                    'title'       => 'Modul Inventaris Aset',
                    'description' => 'Pencatatan QR code dan riwayat mutasi aset',
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/proposals', $payload);

        $response->assertCreated();
        $proposalId = $response->json('id');
        $proposalCode = $response->json('proposal_code');

        // Pastikan setiap pengurus TeFa mendapatkan notifikasi masing-masing
        $this->assertCount(3, $this->pengurusList);

        foreach ($this->pengurusList as $pengurus) {
            $this->assertDatabaseHas('notifications', [
                'user_id'     => $pengurus->id,
                'type'        => 'proposal.submitted',
                'proposal_id' => $proposalId,
            ]);

            $notification = Notification::where('user_id', $pengurus->id)
                ->where('proposal_id', $proposalId)
                ->first();

            $this->assertNotNull($notification);
            $this->assertEquals('Proposal Baru Masuk', $notification->title);
            $this->assertStringContainsString($proposalCode, $notification->message);
            $this->assertStringContainsString('Sistem Informasi Manajemen Aset', $notification->message);
            $this->assertNull($notification->read_at);
        }

        // Total notifikasi yang tercipta untuk event ini tepat 3 (sesuai jumlah pengurus_tefa)
        $totalNotifications = Notification::where('proposal_id', $proposalId)
            ->where('type', 'proposal.submitted')
            ->count();
        $this->assertEquals(3, $totalNotifications);
    }

    public function test_client_can_submit_proposal_with_warranty_and_delivery_type(): void
    {
        Sanctum::actingAs($this->klien);

        $payload = [
            'client_id'        => $this->klien->id,
            'title'            => 'Aplikasi HRIS Cloud',
            'background'       => 'Sistem pencatatan absensi dan payroll karyawan berbasis cloud untuk kantor pusat dan cabang.',
            'target_audience'  => 'HR dan seluruh karyawan',
            'category_id'      => $this->category->id,
            'delivery_type'    => 'tefa_managed',
            'has_warranty'     => true,
            'warranty_months'  => 6,
            'desired_deadline' => now()->addDays(60)->toDateString(),
            'estimated_budget' => 30000000,
            'modules'          => [
                ['title' => 'Absensi', 'description' => 'Geotagging mobile'],
            ],
        ];

        $response = $this->postJson('/api/v1/proposals', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('proposals', [
            'id'              => $response->json('id'),
            'delivery_type'   => 'tefa_managed',
            'has_warranty'    => true,
            'warranty_months' => 6,
        ]);
        $this->assertDatabaseHas('project_maintenances', [
            'proposal_id'     => $response->json('id'),
            'type'            => 'warranty',
            'duration_months' => 6,
            'status'          => 'pending',
        ]);
    }

    public function test_client_can_choose_source_code_with_maintenance_warranty(): void
    {
        Sanctum::actingAs($this->klien);

        // Kasus: Mau dilepas ke klien (source_code) tp tetep bisa ada garansi cacat 3 bulan
        $payload = [
            'client_id'        => $this->klien->id,
            'title'            => 'Web Company Profile & Source Code',
            'background'       => 'Website instansi dengan penyerahan source code ke klien dan garansi maintenance 3 bulan.',
            'category_id'      => $this->category->id,
            'delivery_type'    => 'source_code',
            'has_warranty'     => true,
            'warranty_months'  => 3,
            'desired_deadline' => now()->addDays(45)->toDateString(),
            'estimated_budget' => 15000000,
        ];

        $response = $this->postJson('/api/v1/proposals', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('proposals', [
            'id'              => $response->json('id'),
            'delivery_type'   => 'source_code',
            'has_warranty'    => true,
            'warranty_months' => 3,
        ]);
        $this->assertDatabaseHas('project_maintenances', [
            'proposal_id'     => $response->json('id'),
            'type'            => 'warranty',
            'duration_months' => 3,
            'status'          => 'pending',
        ]);
    }

    public function test_client_can_specify_dynamic_maintenance_up_to_36_months_regardless_of_delivery_type(): void
    {
        Sanctum::actingAs($this->klien);

        $payload = [
            'client_id'          => $this->klien->id,
            'title'              => 'Enterprise Portal with 36 Months Maintenance',
            'background'         => 'Portal enterprise dengan serah terima source code dan kontrak maintenance 36 bulan.',
            'category_id'        => $this->category->id,
            'delivery_type'      => 'source_code',
            'has_warranty'       => true,
            'warranty_months'    => 6,
            'maintenance_months' => 36,
            'desired_deadline'   => now()->addDays(60)->toDateString(),
            'estimated_budget'   => 80000000,
        ];

        $response = $this->postJson('/api/v1/proposals', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('proposals', [
            'id'                 => $response->json('id'),
            'delivery_type'      => 'source_code',
            'has_warranty'       => true,
            'warranty_months'    => 6,
            'maintenance_months' => 36,
        ]);
        $this->assertDatabaseHas('project_maintenances', [
            'proposal_id'     => $response->json('id'),
            'type'            => 'warranty',
            'duration_months' => 6,
        ]);
        $this->assertDatabaseHas('project_maintenances', [
            'proposal_id'     => $response->json('id'),
            'type'            => 'maintenance',
            'duration_months' => 36,
            'status'          => 'pending',
        ]);
    }

    public function test_warranty_requires_months_when_has_warranty_is_true(): void
    {
        Sanctum::actingAs($this->klien);

        $payload = [
            'client_id'        => $this->klien->id,
            'title'            => 'Uji Validasi Garansi',
            'background'       => 'Deskripsi uji validasi durasi garansi yang wajib diisi jika has_warranty bernilai true.',
            'category_id'      => $this->category->id,
            'delivery_type'    => 'source_code',
            'has_warranty'     => true,
            'warranty_months'  => null,
            'desired_deadline' => now()->addDays(40)->toDateString(),
        ];

        $response = $this->postJson('/api/v1/proposals', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['warranty_months']);
    }
}
