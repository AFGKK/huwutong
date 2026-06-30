<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\License;
use App\Models\MigrationAssistantItem;
use App\Models\MigrationAssistantJob;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * M3-39 AI 迁移助手
 */
class MigrationAssistantService
{
    /**
     * 创建迁移任务
     */
    public function createJob(int $tenantId, int $userId, string $source, array $config = []): MigrationAssistantJob
    {
        return MigrationAssistantJob::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'source' => $source,
            'status' => 'draft',
            'config' => $config,
            'field_mapping' => $config['field_mapping'] ?? config("migration-assistant.sources.{$source}.field_mapping", []),
        ]);
    }

    /**
     * 执行AI辅助导入
     */
    public function runImport(MigrationAssistantJob $job): void
    {
        $job->update(['status' => 'importing', 'started_at' => now()]);

        try {
            // 1. 获取原始数据
            $rawData = $this->fetchSourceData($job);
            $job->update(['total_items' => count($rawData)]);

            foreach ($rawData as $index => $item) {
                $dbItem = MigrationAssistantItem::create([
                    'migration_assistant_job_id' => $job->id,
                    'item_index' => $index,
                    'original_data' => $item,
                    'status' => 'pending',
                ]);

                try {
                    // 2. AI字段映射
                    $mapped = $this->aiMapFields($job, $item);

                    // 3. AI数据清洗
                    $cleaned = $this->aiCleanData($mapped);

                    // 4. AI验证
                    $validation = $this->aiValidate($cleaned);

                    if (!empty($validation['errors'])) {
                        // 5. AI自动修复
                        $fixed = $this->aiAutoFix($cleaned, $validation['errors']);
                        $dbItem->update([
                            'mapped_data' => $mapped,
                            'cleaned_data' => $fixed,
                            'validation_errors' => $validation['errors'],
                            'ai_suggestions' => $validation['suggestions'] ?? [],
                            'status' => !empty($fixed) ? 'fixed' : 'error',
                        ]);

                        if (empty($fixed)) {
                            $job->increment('failed_items');
                            continue;
                        }
                        $cleaned = $fixed;
                    }

                    // 6. 导入
                    $result = $this->importItem($job, $cleaned);
                    $dbItem->update([
                        'mapped_data' => $mapped,
                        'cleaned_data' => $cleaned,
                        'status' => 'imported',
                        'created_license_id' => $result['license_id'],
                        'created_customer_id' => $result['customer_id'],
                    ]);
                    $job->increment('imported_items');
                } catch (\Exception $e) {
                    $dbItem->update([
                        'status' => 'error',
                        'validation_errors' => ['error' => $e->getMessage()],
                    ]);
                    $job->increment('failed_items');
                }
            }

            $job->update([
                'status' => 'completed',
                'completed_at' => now(),
                'summary' => [
                    'total' => $job->total_items,
                    'imported' => $job->imported_items,
                    'failed' => $job->failed_items,
                    'skipped' => $job->skipped_items,
                ],
            ]);
        } catch (\Exception $e) {
            $job->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);
            Log::error('Migration assistant failed', ['job_id' => $job->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * 从源系统拉取数据
     */
    protected function fetchSourceData(MigrationAssistantJob $job): array
    {
        $source = $job->source;
        $apiKey = $job->config['api_key'] ?? '';

        return match ($source) {
            'cryptlex' => $this->fetchCryptlex($apiKey),
            'localazy' => $this->fetchLocalazy($apiKey),
            default => [],
        };
    }

    protected function fetchCryptlex(string $apiKey): array
    {
        $baseUrl = config('migration-assistant.sources.cryptlex.api_base');
        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->get("{$baseUrl}/licenses", ['limit' => 200]);

        if (!$response->successful()) {
            throw new \RuntimeException('Cryptlex API请求失败: ' . $response->body());
        }

        return $response->json('results', $response->json('data', []));
    }

    protected function fetchLocalazy(string $apiKey): array
    {
        $baseUrl = config('migration-assistant.sources.localazy.api_base');
        $response = Http::withHeaders(['Authorization' => "Bearer {$apiKey}"])
            ->timeout(60)
            ->get("{$baseUrl}/licenses", ['limit' => 200]);

        if (!$response->successful()) {
            throw new \RuntimeException('Localazy API请求失败: ' . $response->body());
        }

        return $response->json('data', $response->json('licenses', []));
    }

    /**
     * AI字段映射
     */
    protected function aiMapFields(MigrationAssistantJob $job, array $rawItem): array
    {
        $mapping = $job->field_mapping ?: config("migration-assistant.sources.{$job->source}.field_mapping", []);
        $mapped = [];

        foreach ($mapping as $sourceField => $targetField) {
            // 支持点号嵌套访问
            $value = $this->arrayGet($rawItem, $sourceField);
            if ($value !== null) {
                $mapped[$targetField] = $value;
            }
        }

        // AI辅助映射（对未匹配字段）
        $unmapped = array_diff_key($rawItem, array_flip(array_keys($mapping)));
        if (!empty($unmapped) && config('migration-assistant.ai.enabled')) {
            $aiMapping = $this->suggestMapping($unmapped, $mapped);
            foreach ($aiMapping as $key => $value) {
                if (!isset($mapped[$key])) {
                    $mapped[$key] = $value;
                }
            }
        }

        return $mapped;
    }

    /**
     * AI数据清洗
     */
    protected function aiCleanData(array $mapped): array
    {
        $cleaned = $mapped;

        // 状态标准化
        if (isset($cleaned['status'])) {
            $statusMap = [
                'active' => 'active', 'ACTIVE' => 'active', '1' => 'active', 'true' => 'active',
                'expired' => 'expired', 'EXPIRED' => 'expired', '0' => 'inactive',
                'revoked' => 'revoked', 'REVOKED' => 'revoked',
                'suspended' => 'suspended', 'SUSPENDED' => 'suspended',
                'trial' => 'trial', 'TRIAL' => 'trial', 'trialing' => 'trial',
            ];
            $cleaned['status'] = $statusMap[$cleaned['status']] ?? 'active';
        }

        // License Key清理
        if (isset($cleaned['license_key'])) {
            $cleaned['license_key'] = trim($cleaned['license_key']);
        }

        // 日期标准化
        foreach (['expires_at', 'created_at'] as $dateField) {
            if (!empty($cleaned[$dateField])) {
                try {
                    $cleaned[$dateField] = date('Y-m-d H:i:s', strtotime($cleaned[$dateField]));
                } catch (\Exception) {
                    // 保持原值
                }
            }
        }

        return $cleaned;
    }

    /**
     * AI验证
     */
    protected function aiValidate(array $cleaned): array
    {
        $errors = [];
        $suggestions = [];

        // License Key验证
        if (empty($cleaned['license_key'])) {
            $errors['license_key'] = 'License Key不能为空';
            $suggestions[] = '将使用自动生成的Key';
            $cleaned['license_key'] = 'MIG-' . strtoupper(Str::random(20));
        }

        // 状态验证
        $validStatuses = ['active', 'expired', 'revoked', 'suspended', 'trial', 'inactive'];
        if (!empty($cleaned['status']) && !in_array($cleaned['status'], $validStatuses)) {
            $errors['status'] = "无效状态: {$cleaned['status']}";
            $suggestions[] = "状态已设为'active'";
            $cleaned['status'] = 'active';
        }

        // 日期验证
        if (!empty($cleaned['expires_at']) && !strtotime($cleaned['expires_at'])) {
            $errors['expires_at'] = "无效日期: {$cleaned['expires_at']}";
            $suggestions[] = '日期格式已修正';
            $cleaned['expires_at'] = null;
        }

        return ['errors' => $errors, 'suggestions' => $suggestions];
    }

    /**
     * AI自动修复
     */
    protected function aiAutoFix(array $cleaned, array $errors): ?array
    {
        if (!config('migration-assistant.validation.auto_fix')) {
            return empty($errors) ? $cleaned : null;
        }

        if (isset($errors['license_key'])) {
            $cleaned['license_key'] = 'MIG-' . strtoupper(Str::random(20));
        }

        return empty($errors) ? $cleaned : (count($errors) > 3 ? null : $cleaned);
    }

    /**
     * 导入到本地系统
     */
    protected function importItem(MigrationAssistantJob $job, array $data): array
    {
        $tenantId = $job->tenant_id;
        $customerId = null;

        // 创建客户
        if (!empty($data['customer_email'])) {
            $customer = Customer::firstOrCreate(
                ['email' => $data['customer_email'], 'tenant_id' => $tenantId],
                [
                    'tenant_id' => $tenantId,
                    'name' => $data['customer_name'] ?? explode('@', $data['customer_email'])[0],
                    'email' => $data['customer_email'],
                ]
            );
            $customerId = $customer->id;
        }

        // 创建License
        $license = License::create([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'license_key' => $data['license_key'] ?? ('MIG-' . strtoupper(Str::random(20))),
            'status' => $data['status'] ?? 'active',
            'expires_at' => $data['expires_at'] ?? null,
            'max_devices' => $data['max_devices'] ?? 1,
            'product_id' => $this->resolveProduct($tenantId, $data['product_name'] ?? null),
            'metadata' => array_merge(
                $data['metadata'] ?? [],
                ['migrated_by' => 'ai_assistant', 'source' => $job->source, 'job_id' => $job->id]
            ),
        ]);

        return ['license_id' => $license->id, 'customer_id' => $customerId];
    }

    /**
     * AI建议字段映射
     */
    protected function suggestMapping(array $unmapped, array $currentMapped): array
    {
        $suggested = [];
        $fieldHints = [
            'email' => 'customer_email', 'mail' => 'customer_email',
            'userEmail' => 'customer_email', 'user_email' => 'customer_email',
            'name' => 'customer_name', 'userName' => 'customer_name',
            'product' => 'product_name', 'productName' => 'product_name',
            'machines' => 'max_devices', 'deviceLimit' => 'max_devices',
            'maxMachines' => 'max_devices', 'seats' => 'max_devices',
            'type' => 'license_type', 'licenseType' => 'license_type',
        ];

        foreach ($unmapped as $key => $value) {
            if (isset($fieldHints[$key]) && !isset($currentMapped[$fieldHints[$key]])) {
                $suggested[$fieldHints[$key]] = $value;
            }
        }

        return $suggested;
    }

    protected function resolveProduct(int $tenantId, ?string $productName): ?int
    {
        if (empty($productName)) return null;

        $product = Product::where('tenant_id', $tenantId)
            ->where(function ($q) use ($productName) {
                $q->where('name', $productName)
                  ->orWhere('code', $productName);
            })->first();

        if ($product) return $product->id;

        if (config('migration-assistant.migration.create_missing_products')) {
            $product = Product::create([
                'tenant_id' => $tenantId,
                'name' => $productName,
                'code' => Str::slug($productName),
                'status' => 'active',
            ]);
            return $product->id;
        }

        return null;
    }

    protected function arrayGet(array $array, string $key): mixed
    {
        if (str_contains($key, '.')) {
            $keys = explode('.', $key);
            foreach ($keys as $k) {
                if (!isset($array[$k])) return null;
                $array = $array[$k];
            }
            return $array;
        }
        return $array[$key] ?? null;
    }

    /**
     * 获取仪表盘
     */
    public function getDashboard(int $tenantId): array
    {
        $total = MigrationAssistantJob::where('tenant_id', $tenantId)->count();
        $completed = MigrationAssistantJob::where('tenant_id', $tenantId)->where('status', 'completed')->count();
        $failed = MigrationAssistantJob::where('tenant_id', $tenantId)->where('status', 'failed')->count();
        $totalImported = MigrationAssistantJob::where('tenant_id', $tenantId)->sum('imported_items');

        return compact('total', 'completed', 'failed', 'totalImported');
    }

    /**
     * 获取任务报告
     */
    public function getReport(MigrationAssistantJob $job): array
    {
        $job->load('items');
        $failedItems = $job->items->whereIn('status', ['error', 'pending'])->values()->toArray();

        return [
            'job' => $job->toArray(),
            'summary' => [
                'total' => $job->total_items,
                'imported' => $job->imported_items,
                'failed' => $job->failed_items,
                'success_rate' => $job->total_items > 0
                    ? round(($job->imported_items / $job->total_items) * 100, 1)
                    : 0,
            ],
            'failed_items' => $failedItems,
        ];
    }
}
