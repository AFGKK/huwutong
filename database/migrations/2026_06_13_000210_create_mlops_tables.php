<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ml_models')) {
            return;
        }
        Schema::create('ml_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('model_key', 100)->unique()->comment('唯一标识');
            $table->string('framework', 50)->comment('tensorflow/pytorch/onnx/sklearn/xgboost');
            $table->string('task_type', 50)->comment('classification/regression/anomaly_detection/recommendation');
            $table->text('description')->nullable();
            $table->string('status', 30)->default('active')->comment('active/archived/deprecated');
            $table->json('config')->nullable()->comment('模型配置');
            $table->json('features')->nullable()->comment('输入特征定义');
            $table->json('metrics_definitions')->nullable()->comment('指标定义');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ml_model_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ml_model_id')->constrained('ml_models')->cascadeOnDelete();
            $table->string('version', 30)->comment('语义版本 v1.0.0');
            $table->string('file_path', 500)->comment('模型文件路径');
            $table->string('file_hash', 64)->comment('SHA256');
            $table->unsignedInteger('file_size')->comment('bytes');
            $table->json('metrics')->nullable()->comment('评估指标');
            $table->json('hyperparameters')->nullable()->comment('超参数');
            $table->string('status', 30)->default('staging')->comment('staging/production/archived/failed');
            $table->timestamp('deployed_at')->nullable();
            $table->foreignId('deployed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ml_training_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ml_model_id')->constrained('ml_models')->cascadeOnDelete();
            $table->string('job_id', 100)->unique();
            $table->string('status', 30)->default('pending')->comment('pending/running/completed/failed');
            $table->json('config')->nullable();
            $table->json('results')->nullable();
            $table->float('duration_seconds')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ml_drift_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ml_model_version_id')->constrained('ml_model_versions')->cascadeOnDelete();
            $table->string('metric', 50);
            $table->float('baseline_value');
            $table->float('current_value');
            $table->float('drift_value');
            $table->string('severity', 20)->default('warning')->comment('info/warning/critical');
            $table->boolean('auto_retrain_triggered')->default(false);
            $table->timestamp('detected_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ml_drift_events');
        Schema::dropIfExists('ml_training_jobs');
        Schema::dropIfExists('ml_model_versions');
        Schema::dropIfExists('ml_models');
    }
};
