<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah overall_progress di proposals
        Schema::table('proposals', function (Blueprint $table) {
            $table->decimal('overall_progress', 5, 2)->default(0.00)->after('status_id');
        });

        // 2. Hitung initial overall_progress untuk data yang sudah ada
        DB::table('proposals')->orderBy('id')->each(function ($proposal) {
            $avg = DB::table('tasks')
                ->where('proposal_id', $proposal->id)
                ->avg('progress_percentage');

            DB::table('proposals')
                ->where('id', $proposal->id)
                ->update(['overall_progress' => $avg !== null ? round((float) $avg, 2) : 0.00]);
        });

        // 3. Drop category_id di tasks
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('project_team_id')->constrained('task_categories')->nullOnDelete();
        });

        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn('overall_progress');
        });
    }
};
