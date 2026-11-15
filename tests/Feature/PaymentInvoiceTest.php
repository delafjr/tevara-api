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

class PaymentInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_gets_a_persistent_invoice_number_and_client_can_download_invoice(): void
    {
        Role::firstOrCreate(['name' => 'klien', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'dosen', 'guard_name' => 'web']);
        $client = User::factory()->create();
        $client->assignRole('klien');
        $lecturer = User::factory()->create();
        $lecturer->assignRole('dosen');
        $status = ProposalStatus::firstOrCreate(['name' => 'Active']);
        $category = ProposalCategory::firstOrCreate(['name' => 'Web']);
        $proposal = Proposal::create([
            'client_id' => $client->id,
            'title' => 'Project invoice test',
            'background' => 'Invoice test project description.',
            'category_id' => $category->id,
            'status_id' => $status->id,
        ]);

        Sanctum::actingAs($lecturer);
        $paymentResponse = $this->postJson("/api/v1/proposals/{$proposal->id}/payments", [
            'term_sequence' => 1,
            'amount' => 1250000,
        ]);

        $paymentResponse->assertCreated();
        $invoiceNumber = $paymentResponse->json('data.invoice_number');
        $this->assertMatchesRegularExpression('/^INV\/\d{4}\/\d{6}$/', $invoiceNumber);
        $this->assertDatabaseHas('payments', [
            'id' => $paymentResponse->json('data.id'),
            'invoice_number' => $invoiceNumber,
        ]);

        Sanctum::actingAs($client);
        $this->getJson("/api/v1/proposals/{$proposal->id}")
            ->assertOk()
            ->assertJsonPath('payments.0.invoice_number', $invoiceNumber);
        $this->get("/api/v1/payments/{$paymentResponse->json('data.id')}/invoice")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_client_cannot_download_another_clients_invoice(): void
    {
        Role::firstOrCreate(['name' => 'klien', 'guard_name' => 'web']);
        $owner = User::factory()->create();
        $owner->assignRole('klien');
        $otherClient = User::factory()->create();
        $otherClient->assignRole('klien');
        $status = ProposalStatus::firstOrCreate(['name' => 'Active']);
        $category = ProposalCategory::firstOrCreate(['name' => 'Web']);
        $proposal = Proposal::create([
            'client_id' => $owner->id,
            'title' => 'Private invoice test',
            'background' => 'Private invoice test project.',
            'category_id' => $category->id,
            'status_id' => $status->id,
        ]);
        $payment = $proposal->payments()->create([
            'term_sequence' => 1,
            'amount' => 1250000,
            'invoice_number' => 'INV/'.now()->format('Y').'/000001',
            'status' => 'PENDING',
        ]);

        Sanctum::actingAs($otherClient);
        $this->get("/api/v1/payments/{$payment->id}/invoice")->assertForbidden();
    }
}
