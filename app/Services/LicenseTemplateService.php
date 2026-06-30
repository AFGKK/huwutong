<?php

namespace App\Services;

use App\Models\License;
use App\Models\LicenseBatchGeneration;
use App\Models\LicenseBatchItem;
use App\Models\LicenseTemplate;
use App\Models\LicenseTemplateVariable;
use App\Services\KeyGenerator;
use Illuminate\Support\Facades\DB;

/**
 * License 模板服务
 *
 * 管理模板变量、字段映射、变量替换、批量生成
 */
class LicenseTemplateService
{
    /**
     * 获取模板完整信息（含变量和映射）
     */
    public function getTemplateWithExtras(int $id): LicenseTemplate
    {
        return LicenseTemplate::with(['product:id,name', 'variables', 'fieldMappings'])->findOrFail($id);
    }

    // ─── 模板变量管理 ───

    public function getVariables(int $templateId): array
    {
        return LicenseTemplateVariable::where('license_template_id', $templateId)
            ->orderBy('sort_order')
            ->get()
            ->toArray();
    }

    public function saveVariables(int $templateId, array $variables): void
    {
        LicenseTemplateVariable::where('license_template_id', $templateId)->delete();

        foreach ($variables as $i => $var) {
            LicenseTemplateVariable::create([
                'license_template_id' => $templateId,
                'key' => $var['key'],
                'label' => $var['label'] ?? $var['key'],
                'variable_type' => $var['variable_type'] ?? 'string',
                'options' => $var['options'] ?? null,
                'default_value' => $var['default_value'] ?? null,
                'description' => $var['description'] ?? null,
                'is_required' => $var['is_required'] ?? false,
                'sort_order' => $i,
            ]);
        }
    }

    // ─── 字段映射管理 ───

    public function saveFieldMappings(int $templateId, array $mappings): void
    {
        DB::table('license_template_field_mappings')
            ->where('license_template_id', $templateId)
            ->delete();

        foreach ($mappings as $mapping) {
            DB::table('license_template_field_mappings')->insert([
                'license_template_id' => $templateId,
                'template_field' => $mapping['template_field'],
                'license_field' => $mapping['license_field'],
                'mapping_type' => $mapping['mapping_type'] ?? 'direct',
            ]);
        }
    }

    // ─── 变量替换 ───

    /**
     * 将变量值替换到文本中
     */
    public function substituteVariables(string $text, array $variables): string
    {
        return preg_replace_callback('/\{\{(\w+)\}\}/', function ($matches) use ($variables) {
            return $variables[$matches[1]] ?? $matches[0];
        }, $text);
    }

    /**
     * 根据模板和变量生成 License 数据
     */
    public function applyTemplateWithVariables(LicenseTemplate $template, array $variableValues, array $overrides = []): array
    {
        $data = $template->apply($overrides);

        // 替换 metadata 中的变量
        if ($data['metadata'] && is_array($data['metadata'])) {
            array_walk_recursive($data['metadata'], function (&$value) use ($variableValues) {
                if (is_string($value)) {
                    $value = $this->substituteVariables($value, $variableValues);
                }
            });
        }

        return $data;
    }

    // ─── 批量生成 ───

    /**
     * 批量生成 License
     *
     * @param LicenseTemplate $template
     * @param int $userId
     * @param string $name 任务名称
     * @param array $rows 变量值数组 [['customer_name'=>'...', 'customer_email'=>'...'], ...]
     * @param array $overrides 全局覆盖
     * @return LicenseBatchGeneration
     */
    public function batchGenerate(LicenseTemplate $template, int $userId, string $name, array $rows, array $overrides = []): LicenseBatchGeneration
    {
        $tenantId = $template->tenant_id;
        $customerId = $overrides['customer_id'] ?? null;

        $batch = LicenseBatchGeneration::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'license_template_id' => $template->id,
            'name' => $name,
            'total_count' => count($rows),
            'success_count' => 0,
            'failed_count' => 0,
            'status' => 'processing',
            'variable_values' => $rows,
            'override_rules' => $overrides,
            'started_at' => now(),
        ]);

        $generatedIds = [];
        $successCount = 0;
        $failedCount = 0;

        foreach ($rows as $index => $variables) {
            $item = LicenseBatchItem::create([
                'batch_generation_id' => $batch->id,
                'row_index' => $index,
                'variables' => $variables,
                'status' => 'pending',
            ]);

            try {
                $licenseData = $this->applyTemplateWithVariables($template, $variables, $overrides);
                $licenseData['tenant_id'] = $tenantId;
                $licenseData['license_key'] = KeyGenerator::generate($template->type ?? 'standard');

                if ($customerId) {
                    $licenseData['customer_id'] = $customerId;
                }

                // 如果变量中有 customer_id，覆盖
                if (!empty($variables['customer_id'])) {
                    $licenseData['customer_id'] = (int) $variables['customer_id'];
                }

                $license = License::create($licenseData);

                $item->update([
                    'license_id' => $license->id,
                    'status' => 'success',
                ]);

                $generatedIds[] = $license->id;
                $successCount++;
            } catch (\Exception $e) {
                $item->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
                $failedCount++;
            }
        }

        $status = match (true) {
            $failedCount === 0 => 'completed',
            $successCount === 0 => 'failed',
            default => 'partial',
        };

        $batch->update([
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'generated_license_ids' => $generatedIds,
            'status' => $status,
            'completed_at' => now(),
        ]);

        return $batch->fresh(['items', 'template']);
    }

    /**
     * 获取批量生成任务列表
     */
    public function getBatchTasks(int $tenantId, array $filters = []): array
    {
        $query = LicenseBatchGeneration::with('template:id,name', 'user:id,name')
            ->where('tenant_id', $tenantId)
            ->when($filters['status'] ?? null, fn($q, $v) => $q->where('status', $v))
            ->orderByDesc('created_at');

        return $query->paginate(
            $filters['per_page'] ?? 20,
            ['*'],
            'page',
            $filters['page'] ?? 1
        )->toArray();
    }

    /**
     * 获取批量生成任务详情
     */
    public function getBatchTask(int $id): LicenseBatchGeneration
    {
        return LicenseBatchGeneration::with([
            'template:id,name,type',
            'items' => fn($q) => $q->with('license:id,license_key,name,customer_id,status')
                ->orderBy('row_index'),
        ])->findOrFail($id);
    }

    // ─── 预览 ───

    /**
     * 预览从模板生成的 License 数据
     */
    public function previewGenerate(LicenseTemplate $template, array $variableValues, array $overrides = []): array
    {
        $results = [];
        foreach ($variableValues as $variables) {
            $results[] = $this->applyTemplateWithVariables($template, $variables, $overrides);
        }
        return $results;
    }
}
