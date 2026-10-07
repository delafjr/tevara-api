<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->unique()->constrained('proposals')->cascadeOnDelete();
            $table->integer('maintenance_warranty_months')->default(3);
            $table->date('maintenance_start_date')->nullable();
            $table->date('maintenance_end_date')->nullable();
            $table->text('maintenance_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_maintenances');
    }
};