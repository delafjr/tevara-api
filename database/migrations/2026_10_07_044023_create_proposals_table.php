<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->string('proposal_code', 50)->unique();

            // F-01: Pengajuan
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('background');
            $table->text('target_audience')->nullable();
            $table->foreignId('category_id')->constrained('proposal_categories');
            $table->date('desired_deadline')->nullable();
            $table->decimal('estimated_budget', 15, 2)->nullable();
            $table->string('attachment_url')->nullable();

            // F-02: Review Kelayakan
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('complexity_level', ['LOW', 'MEDIUM', 'HIGH'])->nullable();
            $table->text('risk_analysis')->nullable();
            $table->decimal('estimated_cost', 15, 2)->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            // F-03: Kontrak / SPK
            $table->string('spk_number', 100)->unique()->nullable();
            $table->text('scope_of_work')->nullable();
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->text('payment_terms_description')->nullable();
            $table->string('contract_file_url')->nullable();
            $table->boolean('is_signed')->default(false);
            $table->timestamp('signed_at')->nullable();

            // Project Details
            $table->string('project_code', 50)->unique()->nullable();
            $table->foreignId('pm_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('target_completion_date')->nullable();
            $table->date('actual_completion_date')->nullable();

            // F-07: BAST
            $table->string('bast_number', 100)->unique()->nullable();
            $table->string('repository_url')->nullable();
            $table->string('documentation_url')->nullable();
            $table->string('user_manual_url')->nullable();
            $table->text('handover_notes')->nullable();
            $table->date('handover_date')->nullable();
            $table->string('client_signoff_name')->nullable();
            $table->boolean('client_approved')->default(false);
            $table->timestamp('bast_approved_at')->nullable();

            // Status Global
            $table->foreignId('status_id')->constrained('proposal_statuses');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposals');
    }
};