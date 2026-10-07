<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('company_name')->nullable();
            $table->text('address')->nullable();
            $table->enum('client_type', ['EXTERNAL', 'INTERNAL'])->default('EXTERNAL');
            $table->string('signature_url')->nullable();
            $table->timestamps();
        });

        Schema::create('lecturer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('nidn', 20)->unique()->nullable();
            $table->timestamps();
        });

        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('nim', 20)->unique();
            $table->string('study_program', 100)->default('D3 Teknik Informatika');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
        Schema::dropIfExists('lecturer_profiles');
        Schema::dropIfExists('client_profiles');
    }
};