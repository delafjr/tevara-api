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

class ProposalRevisionTest extends TestCase
{
    use RefreshDatabase;

    private User $pengurus;
    private User $klien;
    private Proposal $proposal;
    private ProposalCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        // Roles
        Role::firstOrCreate(['name' => 'pengurus_tefa', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'klien', 'guard_name' => 'web']);

        // Statuses
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

        $this->pengurus = User::factory()->create(['name' => 'Budi Pengurus TEFA', 'email' => 'pengurus@tefa.id']);
        $this->pengurus->assignRole('pengurus_tefa');

        $this->klien = User::factory()->create(['name' => 'Klien PT Maju', 'email' => 'klien@maju.id']);
        $this->klien->assignRole('klien');

        $this->proposal = Proposal::create([
            'proposal_code'    => 'PRSL-2026-001',
            'client_id'        => $this->klien->id,
            'title'            => 'Sistem E-Commerce B2B',
            'background'       => 'Ini adalah ringkasan kebutuhan proyek yang panjang dan melebihi seratus karakter untuk memenuhi syarat validasi.',
            'target_audience'  => 'Pelaku usaha retail dan grosir',
            'category_id'      => $this->category->id,
            'desired_deadline' => now()->addDays(60)->toDateString(),
            'estimated_budget' => 50000000,
            'status_id'        => 2, // Submitted
        ]);

        $this->proposal->modules()->create([
            'title'       => 'Modul Checkout & Payment',
            'description' => 'Integrasi gateway pembayaran',
        ]);
    }

    public function test_pengurus_tefa_can_request_revision_and_status_becomes_revision_required(): void
    {
        Sanctum::actingAs($this->pengurus);

        $response = $this->patchJson("/api/v1/proposals/{$this->proposal->id}/review", [
            'decision'     => 'revision_required',
            'review_notes' => 'Mohon sesuaikan estimasi harga/anggaran menjadi maksimal Rp 35.000.000 dan rinci modul pembayarannya.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status_id', 4)
            ->assertJsonPath('data.review_notes', 'Mohon sesuaikan estimasi harga/anggaran menjadi maksimal Rp 35.000.000 dan rinci modul pembayarannya.')
            ->assertJsonPath('data.reviewer.name', 'Budi Pengurus TEFA');

        $this->assertDatabaseHas('proposals', [
            'id'           => $this->proposal->id,
            'status_id'    => 4,
            'reviewer_id'  => $this->pengurus->id,
            'review_notes' => 'Mohon sesuaikan estimasi harga/anggaran menjadi maksimal Rp 35.000.000 dan rinci modul pembayarannya.',
        ]);

        $this->assertDatabaseHas('proposal_status_histories', [
            'proposal_id' => $this->proposal->id,
            'status_id'   => 4,
            'note'        => 'Mohon sesuaikan estimasi harga/anggaran menjadi maksimal Rp 35.000.000 dan rinci modul pembayarannya.',
            'changed_by'  => $this->pengurus->id,
        ]);

        // Verifikasi notifikasi terkirim ke PIC klien
        $this->assertDatabaseHas('notifications', [
            'user_id'     => $this->klien->id,
            'type'        => 'proposal.revision_required',
            'proposal_id' => $this->proposal->id,
        ]);

        $notification = \App\Models\Notification::where('user_id', $this->klien->id)
            ->where('proposal_id', $this->proposal->id)
            ->where('type', 'proposal.revision_required')
            ->latest('id')
            ->first();

        $this->assertNotNull($notification);
        $this->assertEquals('Proposal Perlu Revisi', $notification->title);
        $this->assertStringContainsString('Rp 35.000.000', $notification->message);
    }

    public function test_klien_can_view_proposal_and_submit_revision_which_returns_status_to_submitted(): void
    {
        // Set proposal to Revision Required (status 4) with review notes
        $this->proposal->update([
            'status_id'    => 4,
            'reviewer_id'  => $this->pengurus->id,
            'review_notes' => 'Mohon ubah harga menjadi 35000000',
        ]);

        Sanctum::actingAs($this->klien);

        // Klien sees proposal in proposals/mine
        $mineResponse = $this->getJson('/api/v1/proposals/mine');
        $mineResponse->assertOk()
            ->assertJsonFragment([
                'id'           => $this->proposal->id,
                'status_id'    => 4,
                'review_notes' => 'Mohon ubah harga menjadi 35000000',
            ]);

        // Klien views detail
        $showResponse = $this->getJson("/api/v1/proposals/{$this->proposal->id}");
        $showResponse->assertOk()
            ->assertJsonPath('review_notes', 'Mohon ubah harga menjadi 35000000');

        // Klien submits revision (e.g. adjusts price to 35000000)
        $updateResponse = $this->putJson("/api/v1/proposals/{$this->proposal->id}", [
            'title'            => 'Sistem E-Commerce B2B (Revisi)',
            'background'       => 'Ini adalah ringkasan kebutuhan proyek yang diperbarui dan melebihi seratus karakter untuk memenuhi syarat validasi.',
            'target_audience'  => 'Pelaku usaha retail dan distributor resmi',
            'category_id'      => $this->category->id,
            'desired_deadline' => now()->addDays(60)->toDateString(),
            'estimated_budget' => 35000000,
            'modules'          => [
                [
                    'title'       => 'Modul Checkout & Payment (Disinkronkan)',
                    'description' => 'Integrasi gateway pembayaran QRIS dan Virtual Account',
                ],
            ],
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.status_id', 2);
        $this->assertEquals(35000000, (float) $updateResponse->json('data.estimated_budget'));

        $this->assertDatabaseHas('proposals', [
            'id'               => $this->proposal->id,
            'status_id'        => 2,
            'estimated_budget' => 35000000,
        ]);

        // Pengurus TEFA can see proposal back in pending
        Sanctum::actingAs($this->pengurus);
        $pendingResponse = $this->getJson('/api/v1/proposals/pending');
        $pendingResponse->assertOk();
        $this->assertTrue(
            collect($pendingResponse->json())->contains(fn ($p) => $p['id'] === $this->proposal->id && (float) $p['estimated_budget'] === 35000000.0)
        );
    }

    public function test_pengurus_tefa_can_reject_proposal_with_reason_and_creates_notification_for_pic(): void
    {
        Sanctum::actingAs($this->pengurus);

        $response = $this->patchJson("/api/v1/proposals/{$this->proposal->id}/review", [
            'decision'     => 'rejected',
            'review_notes' => 'Ruang lingkup proyek tidak sesuai dengan fokus kompetensi TeFa saat ini.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status_id', 6)
            ->assertJsonPath('data.review_notes', 'Ruang lingkup proyek tidak sesuai dengan fokus kompetensi TeFa saat ini.');

        $this->assertDatabaseHas('proposals', [
            'id'           => $this->proposal->id,
            'status_id'    => 6,
            'reviewer_id'  => $this->pengurus->id,
            'review_notes' => 'Ruang lingkup proyek tidak sesuai dengan fokus kompetensi TeFa saat ini.',
        ]);

        // Verifikasi notifikasi terkirim ke PIC klien
        $this->assertDatabaseHas('notifications', [
            'user_id'     => $this->klien->id,
            'type'        => 'proposal.rejected',
            'proposal_id' => $this->proposal->id,
        ]);

        $notification = \App\Models\Notification::where('user_id', $this->klien->id)
            ->where('proposal_id', $this->proposal->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('Ruang lingkup proyek tidak sesuai', $notification->message);
    }

    public function test_pengurus_tefa_can_reject_proposal_when_status_is_revision_required(): void
    {
        $this->proposal->update([
            'status_id'    => 4,
            'reviewer_id'  => $this->pengurus->id,
            'review_notes' => 'Perlu revisi anggaran',
        ]);

        Sanctum::actingAs($this->pengurus);

        $response = $this->patchJson("/api/v1/proposals/{$this->proposal->id}/review", [
            'decision'     => 'rejected',
            'review_notes' => 'Klien tidak bersedia memenuhi syarat kelayakan teknis.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status_id', 6)
            ->assertJsonPath('data.review_notes', 'Klien tidak bersedia memenuhi syarat kelayakan teknis.');

        $this->assertDatabaseHas('proposals', [
            'id'        => $this->proposal->id,
            'status_id' => 6,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id'     => $this->klien->id,
            'type'        => 'proposal.rejected',
            'proposal_id' => $this->proposal->id,
        ]);
    }

    public function test_rejecting_proposal_requires_reason(): void
    {
        Sanctum::actingAs($this->pengurus);

        $response = $this->patchJson("/api/v1/proposals/{$this->proposal->id}/review", [
            'decision' => 'rejected',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['review_notes']);
    }
}
