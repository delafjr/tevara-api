<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('proposals', 'maintenance_months')) {
            Schema::table('proposals', function (Blueprint $table) {
                $table->unsignedSmallInteger('maintenance_months')->default(0)->after('warranty_months');
            });
        }

        if (!Schema::hasTable('project_maintenances')) {
            Schema::create('project_maintenances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('proposal_id')->constrained('proposals')->cascadeOnDelete();
                $table->enum('type', ['warranty', 'maintenance'])->default('warranty');
                $table->unsignedSmallInteger('duration_months')->default(3);
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->enum('status', ['pending', 'active', 'expired'])->default('pending');
                $table->text('notes')->nullable();
                $table->timestamps();
            });

            // Backfill data garansi bawaan proposal yang sudah ada
            if (Schema::hasTable('proposals')) {
                DB::table('proposals')
                    ->where('has_warranty', true)
                    ->whereNotNull('warranty_months')
                    ->orderBy('id')
                    ->each(function ($proposal) {
                        DB::table('project_maintenances')->insert([
                            'proposal_id'     => $proposal->id,
                            'type'            => 'warranty',
                            'duration_months' => $proposal->warranty_months ?: 3,
                            'start_date'      => $proposal->warranty_start_date,
                            'end_date'        => $proposal->warranty_end_date,
                            'status'          => $proposal->warranty_start_date ? 'active' : 'pending',
                            'notes'           => 'Garansi cacat produk bawaan BAST',
                            'created_at'      => now(),
                            'updated_at'      => now(),
                        ]);
                    });
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_maintenances');

        if (Schema::hasColumn('proposals', 'maintenance_months')) {
            Schema::table('proposals', function (Blueprint $table) {
                $table->dropColumn('maintenance_months');
            });
        }
    }
};
