<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom warranty ke proposals
        Schema::table('proposals', function (Blueprint $table) {
            $table->boolean('has_warranty')->default(false)->after('bast_approved_at');
            $table->unsignedInteger('warranty_months')->nullable()->after('has_warranty');
            $table->date('warranty_start_date')->nullable()->after('warranty_months');
            $table->date('warranty_end_date')->nullable()->after('warranty_start_date');
        });

        // 2. Migrate data dari project_maintenances ke proposals
        if (Schema::hasTable('project_maintenances')) {
            DB::table('project_maintenances')->orderBy('id')->each(function ($row) {
                DB::table('proposals')->where('id', $row->proposal_id)->update([
                    'has_warranty'        => true,
                    'warranty_months'     => $row->maintenance_warranty_months,
                    'warranty_start_date' => $row->maintenance_start_date,
                    'warranty_end_date'   => $row->maintenance_end_date,
                ]);
            });

            Schema::dropIfExists('project_maintenances');
        }

        // 3. Buat tabel maintenance_tickets
        Schema::create('maintenance_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('proposals')->cascadeOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->enum('status', ['open', 'in_progress', 'resolved'])->default('open');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_tickets');

        Schema::create('project_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->unique()->constrained('proposals')->cascadeOnDelete();
            $table->integer('maintenance_warranty_months')->default(3);
            $table->date('maintenance_start_date')->nullable();
            $table->date('maintenance_end_date')->nullable();
            $table->text('maintenance_notes')->nullable();
            $table->timestamps();
        });

        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn(['has_warranty', 'warranty_months', 'warranty_start_date', 'warranty_end_date']);
        });
    }
};
