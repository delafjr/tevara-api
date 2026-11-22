<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uat_items', function (Blueprint $table) {
            $table->string('module_name')->nullable()->after('uat_result_id');
            $table->string('test_category', 100)->nullable()->after('module_name');
        });
    }

    public function down(): void
    {
        Schema::table('uat_items', function (Blueprint $table) {
            $table->dropColumn(['module_name', 'test_category']);
        });
    }
};
