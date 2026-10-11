<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Hapus kolom company_name dari client_profiles
        Schema::table('client_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('client_profiles', 'company_name')) {
                $table->dropColumn('company_name');
            }
        });

        // 2. Buat tabel client_pics (1 Perusahaan/Client Profile bisa punya banyak PIC)
        Schema::create('client_pics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_profile_id')->constrained('client_profiles')->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete(); // Akun login user untuk PIC ini
            $table->string('position', 100)->nullable(); // Jabatan PIC (misal: IT Manager, Project Coordinator)
            $table->timestamps();
        });

        // 3. Sesuaikan tabel proposals (hubungkan ke client_profile_id perusahaan)
        Schema::table('proposals', function (Blueprint $table) {
            if (!Schema::hasColumn('proposals', 'client_profile_id')) {
                $table->foreignId('client_profile_id')->after('client_id')->constrained('client_profiles')->cascadeOnDelete();
            }
            // Bersihkan company_id jika sempat terbuat dari skema percobaan sebelumnya
            if (Schema::hasColumn('proposals', 'company_id')) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            }
        });
    }

    public function down(): void
    {
        // Rollback proposals changes
        Schema::table('proposals', function (Blueprint $table) {
            if (Schema::hasColumn('proposals', 'client_profile_id')) {
                $table->dropForeign(['client_profile_id']);
                $table->dropColumn('client_profile_id');
            }
        });

        // Drop client_pics table
        Schema::dropIfExists('client_pics');

        // Restore company_name in client_profiles
        Schema::table('client_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('client_profiles', 'company_name')) {
                $table->string('company_name')->nullable()->after('user_id');
            }
        });
    }
};