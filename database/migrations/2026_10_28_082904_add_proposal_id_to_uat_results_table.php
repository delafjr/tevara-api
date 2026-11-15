<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uat_results', function (Blueprint $table) {
            // Tambah proposal_id dengan unique constraint (one-to-one UAT per proposal)
            $table->foreignId('proposal_id')
                ->nullable()
                ->after('id')
                ->constrained('proposals')
                ->cascadeOnDelete();

            $table->unique('proposal_id');
        });

        // Tambah due_date ke payments (opsional sesuai referensi F-09)
        Schema::table('payments', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('uat_results', function (Blueprint $table) {
            $table->dropUnique(['proposal_id']);
            $table->dropForeign(['proposal_id']);
            $table->dropColumn('proposal_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('due_date');
        });
    }
};
