<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('invoice_number', 40)->nullable()->unique();
        });

        DB::table('payments')
            ->select(['id', 'created_at'])
            ->orderBy('id')
            ->chunkById(100, function ($payments): void {
                foreach ($payments as $payment) {
                    $year = $payment->created_at
                        ? date('Y', strtotime($payment->created_at))
                        : date('Y');

                    DB::table('payments')
                        ->where('id', $payment->id)
                        ->update([
                            'invoice_number' => sprintf('INV/%s/%06d', $year, $payment->id),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['invoice_number']);
            $table->dropColumn('invoice_number');
        });
    }
};
