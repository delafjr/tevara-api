<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->enum('delivery_type', ['tefa_managed', 'source_code'])
                ->default('source_code')
                ->after('category_id')
                ->comment('tefa_managed = TeFa tetap hosting; source_code = kode diserahkan ke klien');
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn('delivery_type');
        });
    }
};
