<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uat_results', function (Blueprint $table) {
            $table->dropUnique('uat_results_proposal_id_unique');
            $table->string('review_status')->nullable()->after('overall_result');
            $table->text('review_notes')->nullable()->after('client_notes');
            $table->foreignId('submitted_by')->nullable()->after('proposal_id')->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->after('submitted_by')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->index(['proposal_id', 'id']);
        });

        Schema::table('uat_items', function (Blueprint $table) {
            $table->text('review_notes')->nullable()->after('client_feedback');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('uat_item_id')->nullable()->after('proposal_id')->constrained('uat_items')->nullOnDelete();
        });

        DB::table('uat_results')
            ->whereNotNull('signed_off_at')
            ->where('overall_result', 'PASSED')
            ->update(['review_status' => 'APPROVED']);

        DB::table('uat_results')
            ->whereNull('review_status')
            ->update(['review_status' => 'READY_FOR_RETEST']);
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uat_item_id');
        });

        Schema::table('uat_items', function (Blueprint $table) {
            $table->dropColumn('review_notes');
        });

        Schema::table('uat_results', function (Blueprint $table) {
            $table->dropIndex(['proposal_id', 'id']);
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropColumn(['review_status', 'review_notes', 'reviewed_at']);
            $table->unique('proposal_id');
        });
    }
};
