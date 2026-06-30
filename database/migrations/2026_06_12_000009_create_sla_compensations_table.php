<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_compensations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sla_contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sla_breach_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->string('compensation_type', 50)->default('credit')->comment('credit/discount/extension/refund');
            $table->string('severity', 20)->comment('关联违约严重度: minor/major/critical');
            $table->decimal('amount', 12, 2)->default(0)->comment('补偿金额/积分');
            $table->string('currency', 10)->default('CNY');
            $table->text('reason')->nullable()->comment('补偿原因');
            $table->string('calculation_method', 100)->nullable()->comment('计算方式: automatic/manual/formula');

            $table->string('status', 20)->default('pending')->comment('pending/approved/issued/rejected');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status'], 'sla_comp_tenant_status_idx');
            $table->index(['sla_contract_id', 'severity'], 'sla_comp_contract_severity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_compensations');
    }
};
