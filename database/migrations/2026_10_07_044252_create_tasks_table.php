<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('proposals')->cascadeOnDelete();
            $table->foreignId('project_team_id')->nullable()->constrained('project_teams')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('task_categories')->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('priority', ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'])->default('MEDIUM');
            $table->date('due_date')->nullable();
            $table->enum('status', ['TODO', 'IN_PROGRESS', 'TESTING', 'DONE'])->default('TODO');

            // Progress Report
            $table->text('achieved_summary')->nullable();
            $table->text('blockers_summary')->nullable();
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->string('report_attachment_url')->nullable();

            // Review PM
            $table->text('pm_notes')->nullable();
            $table->enum('pm_status', ['PENDING', 'APPROVED', 'NEEDS_IMPROVEMENT'])->default('PENDING');
            $table->timestamp('pm_reviewed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};