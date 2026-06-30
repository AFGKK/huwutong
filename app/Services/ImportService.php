<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\ImportFieldMapping;
use App\Models\ImportLog;
use App\Models\ImportMappingTemplate;
use App\Models\ImportTask;
use App\Models\License;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * 批量数据导入服务
 *
 * 工作流程：
 * 1. 上传文件 → 创建 ImportTask (status: uploaded)
 * 2. 解析文件 → 自动检测字段 → 返回预览 (status: preview)
 * 3. 用户配置映射 + 验证 (status: validated)
 * 4. 执行导入 (status: importing → completed/failed)
 */
class ImportService
{
    // ─── 支持的实体类型及字段定义 ───

    public function getEntityTypes(): array
    {
        return ImportTask::ENTITY_TYPES;
    }

    /**
     * 获取某实体类型的可用字段
     */
    public function getEntityFields(string $entityType): array
    {
        $method = 'get' . ucfirst($entityType) . 'Fields';
        return method_exists($this, $method) ? $this->$method() : [];
    }

    protected function getLicensesFields(): array
    {
        return [
            ['key' => 'license_key', 'label' => 'License Key', 'required' => true, 'type' => 'string'],
            ['key' => 'product_id', 'label' => '产品ID', 'required' => true, 'type' => 'integer'],
            ['key' => 'customer_id', 'label' => '客户ID', 'required' => false, 'type' => 'integer'],
            ['key' => 'customer_name', 'label' => '客户名称', 'required' => false, 'type' => 'string'],
            ['key' => 'customer_email', 'label' => '客户邮箱', 'required' => false, 'type' => 'string'],
            ['key' => 'status', 'label' => '状态', 'required' => false, 'type' => 'string'],
            ['key' => 'expires_at', 'label' => '过期时间', 'required' => false, 'type' => 'datetime'],
            ['key' => 'max_activations', 'label' => '最大激活数', 'required' => false, 'type' => 'integer'],
            ['key' => 'notes', 'label' => '备注', 'required' => false, 'type' => 'text'],
            ['key' => 'metadata', 'label' => '元数据(JSON)', 'required' => false, 'type' => 'json'],
        ];
    }

    protected function getCustomersFields(): array
    {
        return [
            ['key' => 'name', 'label' => '客户名称', 'required' => true, 'type' => 'string'],
            ['key' => 'email', 'label' => '邮箱', 'required' => true, 'type' => 'string'],
            ['key' => 'phone', 'label' => '电话', 'required' => false, 'type' => 'string'],
            ['key' => 'company', 'label' => '公司', 'required' => false, 'type' => 'string'],
            ['key' => 'address', 'label' => '地址', 'required' => false, 'type' => 'text'],
            ['key' => 'notes', 'label' => '备注', 'required' => false, 'type' => 'text'],
            ['key' => 'status', 'label' => '状态', 'required' => false, 'type' => 'string'],
        ];
    }

    protected function getSubscriptionsFields(): array
    {
        return [
            ['key' => 'customer_id', 'label' => '客户ID', 'required' => true, 'type' => 'integer'],
            ['key' => 'product_id', 'label' => '产品ID', 'required' => true, 'type' => 'integer'],
            ['key' => 'status', 'label' => '状态', 'required' => false, 'type' => 'string'],
            ['key' => 'amount', 'label' => '金额', 'required' => true, 'type' => 'numeric'],
            ['key' => 'currency', 'label' => '币种', 'required' => false, 'type' => 'string'],
            ['key' => 'billing_cycle', 'label' => '计费周期', 'required' => false, 'type' => 'string'],
            ['key' => 'starts_at', 'label' => '开始时间', 'required' => false, 'type' => 'datetime'],
            ['key' => 'ends_at', 'label' => '结束时间', 'required' => false, 'type' => 'datetime'],
        ];
    }

    protected function getProductsFields(): array
    {
        return [
            ['key' => 'name', 'label' => '产品名称', 'required' => true, 'type' => 'string'],
            ['key' => 'slug', 'label' => '标识', 'required' => true, 'type' => 'string'],
            ['key' => 'description', 'label' => '描述', 'required' => false, 'type' => 'text'],
            ['key' => 'price', 'label' => '价格', 'required' => false, 'type' => 'numeric'],
            ['key' => 'status', 'label' => '状态', 'required' => false, 'type' => 'string'],
        ];
    }

    protected function getTicketsFields(): array
    {
        return [
            ['key' => 'title', 'label' => '标题', 'required' => true, 'type' => 'string'],
            ['key' => 'description', 'label' => '描述', 'required' => false, 'type' => 'text'],
            ['key' => 'customer_id', 'label' => '客户ID', 'required' => true, 'type' => 'integer'],
            ['key' => 'priority', 'label' => '优先级', 'required' => false, 'type' => 'string'],
            ['key' => 'status', 'label' => '状态', 'required' => false, 'type' => 'string'],
        ];
    }

    // ─── 文件上传与解析 ───

    /**
     * 上传文件并创建导入任务
     */
    public function upload(UploadedFile $file, string $entityType, array $options = []): ImportTask
    {
        $user = auth()->user();
        $originalName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());

        $fileType = in_array($extension, ['xlsx', 'xls']) ? 'xlsx' : 'csv';
        $storedName = uniqid('import_') . '_' . time() . '.' . $extension;
        $path = $file->storeAs('imports', $storedName, 'local');

        $task = ImportTask::create([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'name' => pathinfo($originalName, PATHINFO_FILENAME),
            'entity_type' => $entityType,
            'file_type' => $fileType,
            'original_filename' => $originalName,
            'stored_filename' => $storedName,
            'file_size' => $file->getSize(),
            'status' => 'uploaded',
            'options' => array_merge([
                'skip_errors' => false,
                'update_existing' => true,
                'batch_size' => 100,
                'date_format' => 'Y-m-d',
            ], $options),
        ]);

        return $task;
    }

    /**
     * 解析文件：检测字段、读取数据、生成预览
     */
    public function parseFile(ImportTask $task): ImportTask
    {
        $rows = $this->readFile($task);

        if (empty($rows)) {
            $task->update(['status' => 'failed', 'import_result' => ['error' => '文件为空或无法读取']]);
            return $task->fresh();
        }

        $headers = array_keys($rows[0]);
        $totalRows = count($rows);
        $preview = array_slice($rows, 0, 20);

        // 自动检测映射：匹配目标字段
        $entityFields = $this->getEntityFields($task->entity_type);
        $fieldLabels = array_column($entityFields, 'label');
        $fieldKeys = array_column($entityFields, 'key');

        // 移除旧映射，创建自动检测的映射
        $task->mappings()->delete();

        foreach ($headers as $idx => $header) {
            $target = $this->autoDetectField($header, $fieldKeys, $fieldLabels);
            $fieldDef = collect($entityFields)->firstWhere('key', $target);

            ImportFieldMapping::create([
                'import_task_id' => $task->id,
                'source_field' => $header,
                'target_field' => $target ?? '',
                'target_label' => $fieldDef['label'] ?? $header,
                'is_required' => $fieldDef['required'] ?? false,
                'is_identifier' => $target === 'license_key' || $target === 'email',
                'sort_order' => $idx,
                'transform_rules' => [],
            ]);
        }

        $task->update([
            'status' => 'preview',
            'total_rows' => $totalRows,
            'preview_data' => $preview,
            'validation_errors' => null,
        ]);

        return $task->fresh()->load('mappings');
    }

    /**
     * 自动检测字段名匹配
     */
    protected function autoDetectField(string $source, array $keys, array $labels): ?string
    {
        $source = trim(strtolower($source));

        // 精确匹配 label
        foreach ($labels as $i => $label) {
            if (strtolower($label) === $source || str_replace(' ', '_', strtolower($label)) === $source) {
                return $keys[$i];
            }
        }

        // 精确匹配 key
        foreach ($keys as $key) {
            if (strtolower($key) === $source) {
                return $key;
            }
        }

        // 模糊匹配
        foreach ($keys as $key) {
            $normalized = str_replace('_', '', strtolower($key));
            $sourceNorm = str_replace(['_', ' ', '-'], '', $source);
            if ($normalized === $sourceNorm) {
                return $key;
            }
        }

        return null;
    }

    /**
     * 更新字段映射
     */
    public function updateMappings(ImportTask $task, array $mappings): ImportTask
    {
        foreach ($mappings as $mapping) {
            if (isset($mapping['id'])) {
                ImportFieldMapping::where('id', $mapping['id'])
                    ->where('import_task_id', $task->id)
                    ->update([
                        'target_field' => $mapping['target_field'] ?? '',
                        'default_value' => $mapping['default_value'] ?? null,
                        'transform_rules' => $mapping['transform_rules'] ?? [],
                        'is_required' => $mapping['is_required'] ?? false,
                        'is_identifier' => $mapping['is_identifier'] ?? false,
                    ]);
            }
        }

        return $task->fresh()->load('mappings');
    }

    // ─── 验证 ───

    /**
     * 验证预览数据
     */
    public function validate(ImportTask $task): ImportTask
    {
        $rows = $this->readFile($task);
        $mappings = $task->mappings()->where('target_field', '!=', '')->get();
        $errors = [];
        $errorCount = 0;
        $warningCount = 0;
        static $validStatuses = ['active', 'inactive', 'expired', 'suspended', 'pending'];
        static $validPriorities = ['low', 'medium', 'high', 'critical'];

        foreach ($rows as $rowNum => $row) {
            $rowErrors = [];
            $rowWarnings = [];

            foreach ($mappings as $mapping) {
                $rawValue = $row[$mapping->source_field] ?? '';
                $targetField = $mapping->target_field;

                // 必填检查
                if ($mapping->is_required && empty($rawValue) && empty($mapping->default_value)) {
                    $rowErrors[] = "{$mapping->target_label} ({$targetField}) 为必填";
                    continue;
                }

                // 类型验证
                $fieldDef = collect($this->getEntityFields($task->entity_type))
                    ->firstWhere('key', $targetField);
                $fieldType = $fieldDef['type'] ?? 'string';

                if (!empty($rawValue)) {
                    $typeError = $this->validateFieldType($targetField, $rawValue, $fieldType,
                        $task->entity_type, $row, $mapping->default_value);
                    if ($typeError) {
                        $rowErrors[] = $typeError;
                    }
                }

                // 实体特定验证
                if ($targetField === 'status' && !empty($rawValue) && $task->entity_type === 'licenses') {
                    if (!in_array(strtolower($rawValue), $validStatuses)) {
                        $rowWarnings[] = "状态值 '{$rawValue}' 非标准 (active/inactive/expired/suspended)";
                    }
                }
                if ($targetField === 'priority' && !empty($rawValue) && $task->entity_type === 'tickets') {
                    if (!in_array(strtolower($rawValue), $validPriorities)) {
                        $rowWarnings[] = "优先级 '{$rawValue}' 非标准 (low/medium/high/critical)";
                    }
                }
            }

            if (!empty($rowErrors)) {
                $errors[] = ['row' => $rowNum + 2, 'errors' => $rowErrors, 'warnings' => $rowWarnings];
                $errorCount++;
            } elseif (!empty($rowWarnings)) {
                $errors[] = ['row' => $rowNum + 2, 'errors' => [], 'warnings' => $rowWarnings];
                $warningCount++;
            }
        }

        $task->update([
            'status' => 'validated',
            'validation_errors' => [
                'total_rows' => count($rows),
                'error_rows' => $errorCount,
                'warning_rows' => $warningCount,
                'details' => $errors,
            ],
            'error_rows' => $errorCount,
            'warning_rows' => $warningCount,
        ]);

        return $task->fresh();
    }

    protected function validateFieldType(string $field, string $value, string $type,
                                          string $entityType, array $row, ?string $defaultValue): ?string
    {
        return match ($type) {
            'integer' => is_numeric($value) ? null : "{$field} 应为数字",
            'numeric' => is_numeric($value) ? null : "{$field} 应为数值",
            'datetime' => $this->validateDate($value) ? null : "{$field} 日期格式无效",
            'json' => $this->isJson($value) ? null : "{$field} 应为 JSON 格式",
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : "{$field} 邮箱格式无效",
            default => null,
        };
    }

    protected function validateDate(string $value): bool
    {
        if (empty($value)) return true;
        return strtotime($value) !== false;
    }

    protected function isJson(string $value): bool
    {
        if (empty($value)) return true;
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    // ─── 执行导入 ───

    /**
     * 执行数据导入
     */
    public function execute(ImportTask $task): ImportTask
    {
        if ($task->status !== 'validated' && $task->status !== 'preview') {
            $task->update(['status' => 'failed', 'import_result' => ['error' => '请先验证数据']]);
            return $task->fresh();
        }

        $rows = $this->readFile($task);
        $mappings = $task->mappings()->where('target_field', '!=', '')->get();
        $options = $task->options ?? [];
        $batchSize = $options['batch_size'] ?? 100;
        $skipErrors = $options['skip_errors'] ?? false;

        // 清除旧日志
        $task->logs()->delete();

        $task->update([
            'status' => 'importing',
            'started_at' => now(),
            'processed_rows' => 0,
            'success_rows' => 0,
            'error_rows' => 0,
        ]);

        $success = 0;
        $errors = 0;
        $processed = 0;

        DB::beginTransaction();
        try {
            foreach ($rows as $rowNum => $row) {
                $processed++;
                $action = 'failed';
                $level = 'error';
                $message = '';

                try {
                    $result = $this->processRow($task, $mappings, $row, $rowNum);
                    $action = $result['action'];
                    $level = $result['level'];
                    $message = $result['message'] ?? '';

                    if ($result['level'] === 'error') {
                        $errors++;
                    } else {
                        $success++;
                    }

                    // 逐条日志
                    ImportLog::create([
                        'import_task_id' => $task->id,
                        'row_number' => $rowNum + 2,
                        'level' => $level,
                        'action' => $action,
                        'original_data' => $row,
                        'processed_data' => $result['data'] ?? null,
                        'message' => $message,
                    ]);

                } catch (\Throwable $e) {
                    $errors++;
                    ImportLog::create([
                        'import_task_id' => $task->id,
                        'row_number' => $rowNum + 2,
                        'level' => 'error',
                        'action' => 'failed',
                        'original_data' => $row,
                        'message' => $e->getMessage(),
                    ]);

                    if (!$skipErrors) {
                        DB::rollBack();
                        $task->update([
                            'status' => 'failed',
                            'processed_rows' => $processed,
                            'success_rows' => $success,
                            'error_rows' => $errors,
                            'import_result' => ['error' => '导入失败: ' . $e->getMessage(), 'row' => $rowNum + 2],
                        ]);
                        return $task->fresh();
                    }
                }

                // 阶段性更新
                if ($processed % 50 === 0) {
                    $task->update([
                        'processed_rows' => $processed,
                        'success_rows' => $success,
                        'error_rows' => $errors,
                    ]);
                }
            }

            DB::commit();

            $task->update([
                'status' => $errors > 0 ? 'completed' : 'completed',
                'processed_rows' => $processed,
                'success_rows' => $success,
                'error_rows' => $errors,
                'completed_at' => now(),
                'import_result' => [
                    'total' => count($rows),
                    'success' => $success,
                    'errors' => $errors,
                    'skipped' => 0,
                ],
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            $task->update([
                'status' => 'failed',
                'processed_rows' => $processed,
                'success_rows' => $success,
                'error_rows' => $errors,
                'completed_at' => now(),
                'import_result' => ['error' => '导入异常: ' . $e->getMessage()],
            ]);
        }

        return $task->fresh();
    }

    /**
     * 处理单行数据
     */
    protected function processRow(ImportTask $task, $mappings, array $row, int $rowNum): array
    {
        $entityType = $task->entity_type;
        $updateExisting = $task->options['update_existing'] ?? true;

        // 构建数据
        $data = $this->buildRowData($mappings, $row);

        return match ($entityType) {
            'licenses' => $this->processLicenseRow($data, $rowNum, $updateExisting),
            'customers' => $this->processCustomerRow($data, $rowNum, $updateExisting),
            'subscriptions' => $this->processSubscriptionRow($data, $rowNum, $updateExisting),
            'products' => $this->processProductRow($data, $rowNum, $updateExisting),
            'tickets' => $this->processTicketRow($data, $rowNum, $updateExisting),
            default => ['action' => 'skipped', 'level' => 'warning', 'message' => "未知实体类型: {$entityType}", 'data' => $data],
        };
    }

    /**
     * 根据映射构建行数据
     */
    protected function buildRowData($mappings, array $row): array
    {
        $data = [];
        foreach ($mappings as $mapping) {
            $value = $row[$mapping->source_field] ?? $mapping->default_value ?? '';
            $value = $this->applyTransforms($value, $mapping->transform_rules ?? []);
            if ($value !== '' || $mapping->is_required) {
                $data[$mapping->target_field] = $value;
            }
        }
        return $data;
    }

    /**
     * 应用转换规则
     */
    protected function applyTransforms(mixed $value, array $rules): mixed
    {
        if (empty($value)) return $value;

        foreach ($rules as $rule) {
            $type = $rule['type'] ?? null;
            $value = match ($type) {
                'trim' => is_string($value) ? trim($value) : $value,
                'uppercase' => is_string($value) ? strtoupper($value) : $value,
                'lowercase' => is_string($value) ? strtolower($value) : $value,
                'strip_tags' => is_string($value) ? strip_tags($value) : $value,
                default => $value,
            };
        }
        return $value;
    }

    // ─── 各实体导入逻辑 ───

    protected function processLicenseRow(array $data, int $rowNum, bool $updateExisting): array
    {
        $licenseKey = $data['license_key'] ?? null;
        if (!$licenseKey) {
            return ['action' => 'failed', 'level' => 'error', 'message' => '缺少 license_key', 'data' => $data];
        }

        // 查找或创建客户
        if (!empty($data['customer_email']) && empty($data['customer_id'])) {
            $customer = Customer::where('email', $data['customer_email'])->first();
            if (!$customer && !empty($data['customer_name'])) {
                $customer = Customer::create([
                    'name' => $data['customer_name'],
                    'email' => $data['customer_email'],
                ]);
            }
            if ($customer) $data['customer_id'] = $customer->id;
            unset($data['customer_name'], $data['customer_email']);
        }

        $existing = License::where('license_key', $licenseKey)->first();

        if ($existing && $updateExisting) {
            $existing->update($data);
            return ['action' => 'updated', 'level' => 'info', 'message' => '已更新', 'data' => $data];
        } elseif ($existing) {
            return ['action' => 'skipped', 'level' => 'warning', 'message' => '已存在，跳过', 'data' => $data];
        }

        License::create($data);
        return ['action' => 'created', 'level' => 'info', 'message' => '已创建', 'data' => $data];
    }

    protected function processCustomerRow(array $data, int $rowNum, bool $updateExisting): array
    {
        $email = $data['email'] ?? null;
        if (!$email) {
            return ['action' => 'failed', 'level' => 'error', 'message' => '缺少 email', 'data' => $data];
        }

        $existing = Customer::where('email', $email)->first();

        if ($existing && $updateExisting) {
            $existing->update($data);
            return ['action' => 'updated', 'level' => 'info', 'message' => '已更新', 'data' => $data];
        } elseif ($existing) {
            return ['action' => 'skipped', 'level' => 'warning', 'message' => '已存在，跳过', 'data' => $data];
        }

        Customer::create($data);
        return ['action' => 'created', 'level' => 'info', 'message' => '已创建', 'data' => $data];
    }

    protected function processSubscriptionRow(array $data, int $rowNum, bool $updateExisting): array
    {
        if (empty($data['customer_id']) || empty($data['product_id'])) {
            return ['action' => 'failed', 'level' => 'error', 'message' => '缺少 customer_id 或 product_id', 'data' => $data];
        }

        $existing = Subscription::where('customer_id', $data['customer_id'])
            ->where('product_id', $data['product_id'])
            ->first();

        if ($existing && $updateExisting) {
            $existing->update($data);
            return ['action' => 'updated', 'level' => 'info', 'message' => '已更新', 'data' => $data];
        } elseif ($existing) {
            return ['action' => 'skipped', 'level' => 'warning', 'message' => '已存在，跳过', 'data' => $data];
        }

        Subscription::create($data);
        return ['action' => 'created', 'level' => 'info', 'message' => '已创建', 'data' => $data];
    }

    protected function processProductRow(array $data, int $rowNum, bool $updateExisting): array
    {
        $slug = $data['slug'] ?? null;
        if (empty($data['name']) || !$slug) {
            return ['action' => 'failed', 'level' => 'error', 'message' => '缺少 name 或 slug', 'data' => $data];
        }

        $existing = Product::where('slug', $slug)->first();

        if ($existing && $updateExisting) {
            $existing->update($data);
            return ['action' => 'updated', 'level' => 'info', 'message' => '已更新', 'data' => $data];
        } elseif ($existing) {
            return ['action' => 'skipped', 'level' => 'warning', 'message' => '已存在，跳过', 'data' => $data];
        }

        Product::create($data);
        return ['action' => 'created', 'level' => 'info', 'message' => '已创建', 'data' => $data];
    }

    protected function processTicketRow(array $data, int $rowNum, bool $updateExisting): array
    {
        if (empty($data['title']) || empty($data['customer_id'])) {
            return ['action' => 'failed', 'level' => 'error', 'message' => '缺少 title 或 customer_id', 'data' => $data];
        }

        Ticket::create($data);
        return ['action' => 'created', 'level' => 'info', 'message' => '已创建', 'data' => $data];
    }

    // ─── 文件读取 ───

    /**
     * 从存储文件读取数据
     */
    public function readFile(ImportTask $task): array
    {
        $path = Storage::disk('local')->path('imports/' . $task->stored_filename);

        if (!file_exists($path)) {
            return [];
        }

        if ($task->file_type === 'xlsx') {
            return $this->readExcel($path);
        }

        return $this->readCsv($path);
    }

    protected function readCsv(string $path): array
    {
        $rows = [];
        if (($handle = fopen($path, 'r')) !== false) {
            $headers = fgetcsv($handle);
            if ($headers) {
                $headers = array_map('trim', $headers);
                while (($data = fgetcsv($handle)) !== false) {
                    $row = [];
                    foreach ($headers as $i => $header) {
                        $row[$header] = isset($data[$i]) ? trim($data[$i]) : '';
                    }
                    // 跳过空行
                    if (implode('', $row) !== '') {
                        $rows[] = $row;
                    }
                }
            }
            fclose($handle);
        }
        return $rows;
    }

    protected function readExcel(string $path): array
    {
        try {
            $spreadsheet = IOFactory::load($path);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray();

            if (empty($data)) return [];

            $headers = array_map('trim', $data[0]);
            $rows = [];
            for ($i = 1; $i < count($data); $i++) {
                $row = [];
                foreach ($headers as $j => $header) {
                    $row[$header] = isset($data[$i][$j]) ? trim((string)$data[$i][$j]) : '';
                }
                if (implode('', $row) !== '') {
                    $rows[] = $row;
                }
            }
            return $rows;
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ─── 模板 ───

    public function getMappingTemplates(string $entityType = null): array
    {
        $query = ImportMappingTemplate::where('is_system', true);
        if ($entityType) {
            $query->where('entity_type', $entityType);
        }
        return $query->orderBy('sort_order')->orderBy('name')->get()->all();
    }

    public function createMappingTemplate(array $data): ImportMappingTemplate
    {
        return ImportMappingTemplate::create($data);
    }

    public function deleteMappingTemplate(int $id): void
    {
        ImportMappingTemplate::where('id', $id)->where('is_system', false)->delete();
    }

    public function applyMappingTemplate(ImportTask $task, int $templateId): ImportTask
    {
        $template = ImportMappingTemplate::findOrFail($templateId);

        $task->mappings()->delete();

        foreach ($template->mappings as $idx => $map) {
            ImportFieldMapping::create([
                'import_task_id' => $task->id,
                'source_field' => $map['source_field'] ?? '',
                'target_field' => $map['target_field'] ?? '',
                'target_label' => $map['target_label'] ?? '',
                'default_value' => $map['default_value'] ?? null,
                'transform_rules' => $map['transform_rules'] ?? [],
                'is_required' => $map['is_required'] ?? false,
                'is_identifier' => $map['is_identifier'] ?? false,
                'sort_order' => $idx,
            ]);
        }

        if ($template->default_options) {
            $task->update(['options' => array_merge($task->options ?? [], $template->default_options)]);
        }

        return $task->fresh()->load('mappings');
    }

    // ─── 任务管理 ───

    public function getTasks(int $userId, ?string $entityType = null, ?string $status = null): array
    {
        $query = ImportTask::withCount('logs')
            ->where('user_id', $userId);

        if ($entityType) $query->where('entity_type', $entityType);
        if ($status) $query->where('status', $status);

        return $query->orderByDesc('created_at')->get()->all();
    }

    public function getTask(int $id): ImportTask
    {
        return ImportTask::with(['mappings', 'logs' => fn($q) => $q->orderBy('row_number')->limit(500)])
            ->findOrFail($id);
    }

    public function cancelTask(ImportTask $task): void
    {
        if (in_array($task->status, ['uploaded', 'preview', 'validated'])) {
            $task->update(['status' => 'cancelled']);
        }
    }

    public function deleteTask(ImportTask $task): void
    {
        // 删除文件
        Storage::disk('local')->delete('imports/' . $task->stored_filename);
        $task->delete();
    }

    public function getLogs(ImportTask $task, string $level = null, int $page = 1, int $perPage = 50): array
    {
        $query = $task->logs();
        if ($level) $query->where('level', $level);
        return $query->orderBy('row_number')->paginate($perPage, ['*'], 'page', $page)->toArray();
    }

    // ─── 生成 CSV 模板文件 ───

    public function generateTemplate(string $entityType): array
    {
        $fields = $this->getEntityFields($entityType);
        $headers = array_map(fn($f) => $f['label'] ?: $f['key'], $fields);

        // 示例数据行
        $sample = array_map(fn($f) => match ($f['type']) {
            'integer' => '1',
            'numeric' => '99.99',
            'datetime' => '2026-01-01',
            'email' => 'user@example.com',
            'json' => '{"key": "value"}',
            default => '示例' . $f['label'],
        }, $fields);

        return [
            'headers' => $headers,
            'fields' => $fields,
            'sample' => $sample,
            'entity_type' => $entityType,
        ];
    }
}
