<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            // Penerima notifikasi (fan-out: satu baris per user)
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Jenis notifikasi / template key, contoh: proposal.submitted
            $table->string('type', 100);

            // Snapshot teks yang sudah dirender saat notifikasi dibuat
            $table->string('title', 150);
            $table->text('message');

            // Konteks utama untuk redirect. Null untuk notifikasi non-proposal
            // (contoh: pendaftaran klien baru).
            $table->foreignId('proposal_id')->nullable()
                ->constrained('proposals')->cascadeOnDelete();

            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
