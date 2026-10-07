<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uat_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_team_id')->nullable()->constrained('project_teams')->nullOnDelete();
            $table->date('session_date');
            $table->enum('overall_result', ['PASSED', 'FAILED', 'PARTIAL']);
            $table->text('client_notes')->nullable();
            $table->string('client_signoff_name')->nullable();
            $table->timestamp('signed_off_at')->nullable();
            $table->timestamps();
        });

        Schema::create('uat_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uat_result_id')->constrained('uat_results')->cascadeOnDelete();
            $table->string('feature_name');
            $table->text('test_scenario')->nullable();
            $table->enum('result', ['PASSED', 'FAILED', 'BUG_FOUND']);
            $table->enum('bug_severity', ['MINOR', 'MAJOR', 'CRITICAL'])->nullable();
            $table->text('bug_description')->nullable();
            $table->text('client_feedback')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uat_items');
        Schema::dropIfExists('uat_results');
    }
};