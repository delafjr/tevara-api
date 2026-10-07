<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('proposals')->cascadeOnDelete();
            $table->integer('term_sequence')->default(1);
            $table->decimal('amount', 15, 2);
            $table->date('payment_date')->nullable();
            $table->enum('payment_method', ['BANK_TRANSFER', 'VIRTUAL_ACCOUNT', 'CASH', 'OTHER'])->nullable();
            $table->string('transfer_reference')->nullable();
            $table->string('proof_url')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            $table->enum('status', ['PENDING', 'VERIFIED', 'REJECTED'])->default('PENDING');
            $table->timestamps();

            $table->unique(['proposal_id', 'term_sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};