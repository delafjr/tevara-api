<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                if (!Schema::hasColumn('payments', 'payment_type')) {
                    $table->string('payment_type', 30)->default('project')->after('term_sequence');
                }
                if (!Schema::hasColumn('payments', 'description')) {
                    $table->string('description', 255)->nullable()->after('payment_type');
                }
            });
        }

        if (Schema::hasTable('warranty_extension_requests')) {
            Schema::table('warranty_extension_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('warranty_extension_requests', 'price')) {
                    $table->decimal('price', 15, 2)->nullable()->after('months');
                }
                if (!Schema::hasColumn('warranty_extension_requests', 'payment_deadline')) {
                    $table->timestamp('payment_deadline')->nullable()->after('price');
                }
                if (!Schema::hasColumn('warranty_extension_requests', 'payment_id')) {
                    $table->foreignId('payment_id')->nullable()->after('payment_deadline')->constrained('payments')->nullOnDelete();
                }
            });

            // Ubah tipe status menjadi string agar mendukung status penawaran ('offered')
            Schema::table('warranty_extension_requests', function (Blueprint $table) {
                $table->string('status', 30)->default('pending')->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('warranty_extension_requests')) {
            Schema::table('warranty_extension_requests', function (Blueprint $table) {
                if (Schema::hasColumn('warranty_extension_requests', 'payment_id')) {
                    $table->dropForeign(['payment_id']);
                    $table->dropColumn('payment_id');
                }
                if (Schema::hasColumn('warranty_extension_requests', 'payment_deadline')) {
                    $table->dropColumn('payment_deadline');
                }
                if (Schema::hasColumn('warranty_extension_requests', 'price')) {
                    $table->dropColumn('price');
                }
            });
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                if (Schema::hasColumn('payments', 'description')) {
                    $table->dropColumn('description');
                }
                if (Schema::hasColumn('payments', 'payment_type')) {
                    $table->dropColumn('payment_type');
                }
            });
        }
    }
};
