<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            if (Schema::hasColumn('proposals', 'client_profile_id')) {
                $table->dropForeign(['client_profile_id']);
                $table->dropColumn('client_profile_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            if (!Schema::hasColumn('proposals', 'client_profile_id')) {
                $table->foreignId('client_profile_id')->after('client_id')->constrained('client_profiles')->cascadeOnDelete();
            }
        });
    }
};