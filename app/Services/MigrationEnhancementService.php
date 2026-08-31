<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\License;
use App\Models\MigrationImport;
use App\Models\MigrationImportRow;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * M3-71 竞品迁移工具增强
 */
class MigrationEnhancementService
{
    /**
     * 从API源创建导入任务
     */
    public function createApiImport(int $tenantId, int $userId, string $source, array $options = []): MigrationImport
    {
        return MigrationImport::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'source' => $source,
            'status' => 'pending',
            'field_mapping' => $options['field_mapping'] ?? config("migration-enhancement.sources.{$source}.field_mapping", []),
            'options' => $options,
        ]);
    }

    /**
     * 从文件创建导入任务
     */
    public function createFileImport(int $tenantId, int $userId, UploadedFile $file, array $options = []): MigrationImport
    {
        $path = $file->store('migrations/' . date('Ymd'), 'local');

        return MigrationImport::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'source' => 'custom',
            'status' => 'pending',
            'field_mapping' => $options['field_mapping'] ?? [],
            'options' => $options,
            'file_path' => $path,
        ]);
    }

    /**
     * 执行导入
     */
    public function runImport(MigrationImport $import): void
    {
        $import->update(['status' => 'running', 'started_at' => now()]);

        try {
            $rawData = match ($import->source) {
                'keygen' => $this->fetchKeygen($import),
                'licensespring' => $this->fetchLicenseSpring($import),
                'custom' => $this->parseFile($import),
                default => throw new \InvalidArgumentException("Unsupported source: {$import->source}"),
            };

            $import->update(['total_rows' => count($rawData)]);

            $batchSize = config('migration-enhancement.import.batch_size', 100);
            foreach (array_chunk($rawData, $batchSize) as $chunk) {
                DB::transaction(function () use ($import, $chunk) {
                    foreach ($chunk as $index => $row) {
                        $this->processRow($import, $row, $index);
                    }
                });
            }

            $import->update([
                'status' => 'completed',
                'completed_at' => now(),
                'result_summary' => [
                    'total' => $import->total_rows,
                    'success' => $import->success,
                    'failed' => $import->failed,
                    'skipped' => $import->skipped,
                ],
            ]);
        } catch (\Exception $e) {
            $import->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);
            Log::error('Migration import failed', ['import_id' => $import->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * 处理单行数据
     */
    protected function processRow(MigrationImport $import, array $rawRow, int $index): void
    {
        $row = MigrationImportRow::create([
            'migration_import_id' => $import->id,
            'row_number' => $index + 1,
            'original_data' => $rawRow,
            'status' => 'pending',
        ]);

        try {
            $mapped = $this->mapFields($import, $rawRow);

            // 检查重复
            if (config('migration-enhancement.import.skip_duplicates')) {
                $existing = License::where('tenant_id', $import->tenant_id)
                    ->where('license_key', $mapped['license_key'] ?? '')
                    ->exists();
                if ($existing) {
                    $row->update(['status' => 'skipped', 'mapped_data' => $mapped]);
                    $import->increment('skipped');
                    $import->increment('processed');
                    return;
                }
            }

            // 创建客户
            $customerId = null;
            if (config('migration-enhancement.import.create_customers') && !empty($mapped['customer_email'])) {
                $customer = Customer::firstOrCreate(
                    ['email' => $mapped['customer_email'], 'tenant_id' => $import->tenant_id],
                    [
                        'tenant_id' => $import->tenant_id,
                        'name' => $mapped['customer_name'] ?? explode('@', $mapped['customer_email'])[0],
                        'email' => $mapped['customer_email'],
                    ]
                );
                $customerId = $customer->id;
            }

            // 创建License
            $license = License::create([
                'tenant_id' => $import->tenant_id,
                'customer_id' => $customerId,
                'license_key' => $mapped['license_key'] ?? ('MIG-' . strtoupper(Str::random(20))),
                'status' => $this->mapStatus($mapped['status'] ?? 'active'),
                'expires_at' => !empty($mapped['expires_at']) ? $this->parseDate($mapped['expires_at']) : null,
                'product_id' => $this->resolveProductId($import->tenant_id, $mapped['product_code'] ?? null),
                'metadata' => array_merge(
                    $mapped['metadata'] ?? [],
                    ['migrated_from' => $import->source, 'migration_import_id' => $import->id]
                ),
            ]);

            $row->update([
                'status' => 'success',
                'mapped_data' => $mapped,
                'created_license_id' => $license->id,
                'created_customer_id' => $customerId,
            ]);
            $import->increment('success');
        } catch (\Exception $e) {
            $row->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            $import->increment('failed');
        }

        $import->increment('processed');
    }

    /**
     * 字段映射
     */
    protected function mapFields(MigrationImport $import, array $rawRow): array
    {
        $mapping = $import->field_mapping ?: config("migration-enhancement.sources.{$import->source}.field_mapping", []);
        $mapped = [];

        foreach ($mapping as $sourceField => $targetField) {
            $mapped[$targetField] = $rawRow[$sourceField] ?? null;
        }

        return $mapped;
    }

    /**
     * 从Keygen.sh拉取数据
     */
    protected function fetchKeygen(MigrationImport $import): array
    {
        $apiKey = $import->options['api_key'] ?? '';
        if (empty($apiKey)) throw new \RuntimeException(__("app.migration_enhancement.msg_194eb574"));

        $accountId = $import->options['account_id'] ?? '';
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Accept' => 'application/json',
        ])->get("https://api.keygen.sh/v1/accounts/{$accountId}/licenses", [
            'limit' => 100,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException(__("app.migration_enhancement.msg_dbd8f1f7") . $response->body());
        }

        $data = $response->json();
        $licenses = [];

        foreach ($data['data'] ?? [] as $item) {
            $attrs = $item['attributes'] ?? [];
            $licenses[] = [
                'license_key' => $attrs['key'] ?? '',
                'status' => $attrs['status'] ?? 'active',
                'expiry' => $attrs['expiry'] ?? null,
                'metadata' => $attrs['metadata'] ?? [],
                'user_email' => $attrs['email'] ?? '',
                'product_name' => $attrs['product'] ?? '',
            ];
        }

        return $licenses;
    }

    /**
     * 从LicenseSpring拉取数据
     */
    protected function fetchLicenseSpring(MigrationImport $import): array
    {
        $apiKey = $import->options['api_key'] ?? '';
        if (empty($apiKey)) throw new \RuntimeException(__("app.migration_enhancement.msg_8044e90c"));

        $response = Http::withHeaders([
            'X-API-Key' => $apiKey,
            'Accept' => 'application/json',
        ])->get('https://api.licensespring.com/v1/licenses', [
            'page_size' => 100,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException(__("app.migration_enhancement.msg_231ad8c7") . $response->body());
        }

        $data = $response->json();
        $licenses = [];

        foreach ($data['results'] ?? $data['licenses'] ?? [] as $item) {
            $licenses[] = [
                'licenseKey' => $item['licenseKey'] ?? '',
                'licenseStatus' => $item['licenseStatus'] ?? 'ACTIVE',
                'validUntil' => $item['validUntil'] ?? null,
                'customerEmail' => $item['customerEmail'] ?? '',
                'productCode' => $item['productCode'] ?? '',
            ];
        }

        return $licenses;
    }

    /**
     * 解析上传文件
     */
    protected function parseFile(MigrationImport $import): array
    {
        $path = $import->file_path;
        if (!$path || !Storage::disk('local')->exists($path)) {
            throw new \RuntimeException(__("app.migration_enhancement.msg_6be2de8d"));
        }

        $content = Storage::disk('local')->get($path);
        $format = pathinfo($path, PATHINFO_EXTENSION);

        return match ($format) {
            'json' => json_decode($content, true) ?? [],
            'csv' => $this->parseCsv($content),
            default => throw new \RuntimeException(__("app.migration_enhancement.msg_ea9ce381")),
        };
    }

    protected function parseCsv(string $content): array
    {
        $lines = explode("\n", trim($content));
        if (empty($lines)) return [];

        $headers = str_getcsv(array_shift($lines));
        $rows = [];

        foreach ($lines as $line) {
            $values = str_getcsv($line);
            if (count($values) === count($headers)) {
                $rows[] = array_combine($headers, $values);
            }
        }

        return $rows;
    }

    /**
     * 映射状态
     */
    protected function mapStatus(string $sourceStatus): string
    {
        $map = [
            'ACTIVE' => 'active', 'active' => 'active',
            'EXPIRED' => 'expired', 'expired' => 'expired',
            'REVOKED' => 'revoked', 'revoked' => 'revoked',
            'SUSPENDED' => 'suspended', 'suspended' => 'suspended',
            'DISABLED' => 'revoked', 'disabled' => 'revoked',
            'INACTIVE' => 'inactive', 'inactive' => 'inactive',
            'TRIAL' => 'trial', 'trial' => 'trial',
            'FLOATING' => 'active',
        ];

        return $map[$sourceStatus] ?? config('migration-enhancement.import.default_status', 'active');
    }

    protected function parseDate($value): ?string
    {
        if (empty($value)) return null;
        try {
            return date('Y-m-d H:i:s', strtotime($value));
        } catch (\Exception) {
            return null;
        }
    }

    protected function resolveProductId(int $tenantId, ?string $productCode): ?int
    {
        if (empty($productCode)) return null;

        $product = Product::where('tenant_id', $tenantId)
            ->where(function ($q) use ($productCode) {
                $q->where('code', $productCode)
                  ->orWhere('name', 'like', "%{$productCode}%");
            })->first();

        if ($product) return $product->id;

        if (config('migration-enhancement.import.create_products')) {
            $product = Product::create([
                'tenant_id' => $tenantId,
                'name' => $productCode,
                'code' => Str::slug($productCode),
                'status' => 'active',
            ]);
            return $product->id;
        }

        return null;
    }

    /**
     * 获取仪表盘
     */
    public function getDashboard(int $tenantId): array
    {
        $total = MigrationImport::where('tenant_id', $tenantId)->count();
        $completed = MigrationImport::where('tenant_id', $tenantId)->where('status', 'completed')->count();
        $failed = MigrationImport::where('tenant_id', $tenantId)->where('status', 'failed')->count();
        $totalLicenses = MigrationImport::where('tenant_id', $tenantId)->sum('success');

        $bySource = MigrationImport::where('tenant_id', $tenantId)
            ->selectRaw('source, COUNT(*) as count, SUM(success) as licenses')
            ->groupBy('source')
            ->get()
            ->toArray();

        return compact('total', 'completed', 'failed', 'totalLicenses', 'bySource');
    }

    /**
     * 获取导入报告
     */
    public function getReport(MigrationImport $import): array
    {
        $import->load('rows');

        $failedRows = $import->rows->where('status', 'failed')->values()->toArray();
        $skippedRows = $import->rows->where('status', 'skipped')->values()->toArray();

        return [
            'import' => $import->toArray(),
            'summary' => [
                'total' => $import->total_rows,
                'success' => $import->success,
                'failed' => $import->failed,
                'skipped' => $import->skipped,
                'success_rate' => $import->total_rows > 0
                    ? round(($import->success / $import->total_rows) * 100, 2) . '%'
                    : '0%',
            ],
            'failed_rows' => $failedRows,
            'skipped_rows' => $skippedRows,
        ];
    }
}
