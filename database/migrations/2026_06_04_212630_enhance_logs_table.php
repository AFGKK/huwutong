<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            if (! Schema::hasColumn('logs', 'license_id')) {
                $table->foreignId('license_id')->nullable()->after('user_id')
                    ->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('logs', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->after('license_id')
                    ->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('logs', 'device_id')) {
                $table->foreignId('device_id')->nullable()->after('customer_id')
                    ->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('logs', 'product_id')) {
                $table->foreignId('product_id')->nullable()->after('device_id')
                    ->constrained()->nullOnDelete();
            }

            // 复合索引
            $table->index(['license_id', 'created_at'], 'logs_license_created_idx');
            $table->index(['customer_id', 'created_at'], 'logs_customer_created_idx');
            $table->index(['action', 'created_at'], 'logs_action_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            $table->dropIndex('logs_license_created_idx');
            $table->dropIndex('logs_customer_created_idx');
            $table->dropIndex('logs_action_created_idx');

            $table->dropForeign(['license_id']);
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['device_id']);
            $table->dropForeign(['product_id']);

            $table->dropColumn(['license_id', 'customer_id', 'device_id', 'product_id']);
        });
    }
};
