<?php

namespace App\Services;

use App\Models\CrossBorderTransfer;
use App\Models\Dpia;
use App\Models\PersonalDataInventory;
use App\Models\Tenant;
use App\Support\DbSql;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PIPL 个人信息保护法合规服务 (M3-33b)
 *
 * 处理个人信息分类分级、跨境传输评估、DPIA 影响评估。
 */
class PiplComplianceService
{
    /**
     * 个人信息分类（参照 GB/T 35273-2020）
     */
    const CATEGORY_MAP = [
        'person'   => '个人信息',
        'general'  => '一般信息',
        'sensitive' => '敏感个人信息',
        'private'  => '私密信息',
    ];

    const CLASSIFICATION_MAP = [
        'L1' => '一级（公开）',
        'L2' => '二级（内部）',
        'L3' => '三级（敏感）',
        'L4' => '四级（核心）',
    ];

    // ─── 个人信息分类分级 ───

    /**
     * 扫描数据库表结构，生成默认分类分级清单
     */
    public function scanAndClassify(int $tenantId, ?string $connection = null): array
    {
        $tables = $this->getTables($connection);
        $inventories = [];
        $created = 0;

        foreach ($tables as $table) {
            $columns = $this->getTableColumns($table, $connection);
            foreach ($columns as $column) {
                $classification = $this->classifyColumn($table, $column);
                if ($classification === null) continue; // 跳过非个人字段

                $exists = PersonalDataInventory::where('tenant_id', $tenantId)
                    ->where('table_name', $table)
                    ->where('field_name', $column)
                    ->exists();

                if (! $exists) {
                    PersonalDataInventory::create([
                        'tenant_id' => $tenantId,
                        'field_name' => $column,
                        'table_name' => $table,
                        'category' => $classification['category'],
                        'classification' => $classification['level'],
                        'purpose' => $classification['purpose'],
                        'retention_days' => $classification['retention_days'],
                        'is_required' => in_array($column, ['email', 'phone', 'password']),
                        'is_exportable' => $classification['level'] !== 'L4',
                        'is_deletable' => $classification['level'] !== 'L4',
                    ]);
                    $created++;
                    $inventories[] = compact('table', 'column') + $classification;
                }
            }
        }

        Log::info('PIPL: 扫描并分类个人信息', [
            'tenant_id' => $tenantId,
            'tables_scanned' => count($tables),
            'items_created' => $created,
        ]);

        return [
            'tables_scanned' => count($tables),
            'items_created' => $created,
            'items' => $inventories,
        ];
    }

    /**
     * 自动分类字段
     */
    protected function classifyColumn(string $table, string $column): ?array
    {
        $rule = $this->getClassificationRule($table, $column);
        if ($rule === null) return null;

        return $rule;
    }

    /**
     * 获取字段分类规则
     */
    protected function getClassificationRule(string $table, string $column): ?array
    {
        $col = strtolower($column);
        $tbl = strtolower($table);

        // L4 核心数据 - 不可导出/删除
        if (in_array($col, ['password', 'password_history', 'mfa_secret', 'mfa_recovery_codes', 'mfa_recovery_used'])) {
            return ['category' => 'private', 'level' => 'L4', 'purpose' => '账户安全认证', 'retention_days' => '180'];
        }

        // L3 敏感数据
        $sensitiveFields = ['id_card', 'id_number', 'idcard', 'certificate', 'cert_no', 'bank_account', 'bank_card'];
        if (in_array($col, $sensitiveFields)) {
            return ['category' => 'sensitive', 'level' => 'L3', 'purpose' => '实名认证与支付', 'retention_days' => '365'];
        }

        if (str_contains($col, 'id_card') || str_contains($col, 'cert_no') || str_contains($col, 'bank_card')) {
            return ['category' => 'sensitive', 'level' => 'L3', 'purpose' => '实名认证与支付', 'retention_days' => '365'];
        }

        // L2 一般个人信息
        $personFields = ['name', 'email', 'phone', 'mobile', 'address', 'full_address', 'avatar',
            'ip_address', 'last_login_ip', 'wechat', 'qq', 'user_agent',
            'billing_name', 'billing_phone', 'billing_email',
            'billing_address_line1', 'billing_address_line2', 'billing_city', 'billing_state',
        ];
        if (in_array($col, $personFields)) {
            return ['category' => 'person', 'level' => 'L2', 'purpose' => '业务运营与客户服务', 'retention_days' => '365'];
        }

        if ($tbl === 'users' && ! in_array($col, ['id', 'tenant_id', 'created_at', 'updated_at', 'deleted_at',
            'is_active', 'locale', 'timezone', 'email_verified_at', 'phone_verified_at',
            'last_login_at', 'remember_token', 'role',
        ])) {
            return ['category' => 'person', 'level' => 'L2', 'purpose' => '用户账号管理', 'retention_days' => '365'];
        }

        // L1 一般业务数据
        if (in_array($col, ['notes', 'description', 'subject', 'content', 'remark', 'comment',
            'company', 'company_name', 'job_title', 'department',
            'locale', 'timezone', 'language',
        ])) {
            return ['category' => 'general', 'level' => 'L1', 'purpose' => '业务运营', 'retention_days' => '180'];
        }

        return null; // 非个人相关字段
    }

    /**
     * 获取数据库表列表
     */
    protected function getTables(?string $connection = null): array
    {
        return DbSql::listTableNames($connection);
    }

    /**
     * 获取表字段列表
     */
    protected function getTableColumns(string $table, ?string $connection = null): array
    {
        return DB::connection($connection)->getSchemaBuilder()->getColumnListing($table);
    }

    // ─── 跨境传输评估 ───

    /**
     * 创建跨境传输记录
     */
    public function createCrossBorderTransfer(array $data): CrossBorderTransfer
    {
        $result = CrossBorderTransfer::create(array_merge($data, [
            'status' => CrossBorderTransfer::STATUS_ACTIVE,
            'reviewed_at' => now(),
            'next_review_at' => now()->addYear(),
        ]));

        Log::info('PIPL: 新增跨境传输记录', [
            'data_category' => $data['data_category'] ?? '',
            'recipient_country' => $data['recipient_country'] ?? '',
        ]);

        return $result;
    }

    /**
     * 审核跨境传输
     */
    public function reviewCrossBorderTransfer(int $id, string $impactAssessment, int $reviewedBy): CrossBorderTransfer
    {
        $transfer = CrossBorderTransfer::findOrFail($id);
        $transfer->update([
            'impact_assessment' => $impactAssessment,
            'reviewed_at' => now(),
            'next_review_at' => now()->addYear(),
            'reviewed_by' => $reviewedBy,
        ]);

        return $transfer->fresh();
    }

    /**
     * 检查跨境传输是否需要重新评估
     */
    public function getOverdueTransfers(): array
    {
        return CrossBorderTransfer::where('status', CrossBorderTransfer::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('next_review_at')
                  ->orWhere('next_review_at', '<=', now());
            })
            ->get()
            ->toArray();
    }

    // ─── DPIA 数据保护影响评估 ───

    /**
     * 创建 DPIA
     */
    public function createDpia(array $data, int $userId): Dpia
    {
        $dpia = Dpia::create(array_merge($data, [
            'status' => Dpia::STATUS_DRAFT,
            'created_by' => $userId,
        ]));

        Log::info('PIPL: 创建 DPIA', [
            'title' => $data['title'] ?? '',
            'user_id' => $userId,
        ]);

        return $dpia;
    }

    /**
     * 完成 DPIA
     */
    public function completeDpia(int $id, array $data): Dpia
    {
        $dpia = Dpia::findOrFail($id);

        $updateData = array_merge($data, [
            'status' => Dpia::STATUS_COMPLETED,
            'completed_at' => now(),
            'next_review_at' => now()->addYear(),
        ]);

        $dpia->update($updateData);

        Log::info('PIPL: DIPA 评估完成', ['dpia_id' => $id]);

        return $dpia->fresh();
    }

    // ─── 合规统计 ───

    /**
     * 获取 PIPL 合规概览
     */
    public function getStats(): array
    {
        return [
            'inventory' => [
                'total' => PersonalDataInventory::count(),
                'by_category' => PersonalDataInventory::selectRaw('category, count(*) as total')
                    ->groupBy('category')->pluck('total', 'category')->toArray(),
                'by_level' => PersonalDataInventory::selectRaw('classification, count(*) as total')
                    ->groupBy('classification')->pluck('total', 'classification')->toArray(),
            ],
            'cross_border' => [
                'total' => CrossBorderTransfer::count(),
                'active' => CrossBorderTransfer::where('status', CrossBorderTransfer::STATUS_ACTIVE)->count(),
                'overdue' => CrossBorderTransfer::where('status', CrossBorderTransfer::STATUS_ACTIVE)
                    ->where(function ($q) {
                        $q->whereNull('next_review_at')->orWhere('next_review_at', '<=', now());
                    })->count(),
            ],
            'dpia' => [
                'total' => Dpia::count(),
                'draft' => Dpia::where('status', Dpia::STATUS_DRAFT)->count(),
                'in_progress' => Dpia::where('status', Dpia::STATUS_IN_PROGRESS)->count(),
                'completed' => Dpia::where('status', Dpia::STATUS_COMPLETED)->count(),
            ],
        ];
    }

    /**
     * 获取预定义的敏感字段列表
     */
    public function getSensitiveFieldDefinitions(): array
    {
        return [
            'password' => ['category' => 'private', 'level' => 'L4', 'label' => '密码'],
            'mfa_secret' => ['category' => 'private', 'level' => 'L4', 'label' => 'MFA 密钥'],
            'mfa_recovery_codes' => ['category' => 'private', 'level' => 'L4', 'label' => 'MFA 恢复码'],
            'id_card' => ['category' => 'sensitive', 'level' => 'L3', 'label' => '身份证号'],
            'bank_account' => ['category' => 'sensitive', 'level' => 'L3', 'label' => '银行账户'],
            'phone' => ['category' => 'person', 'level' => 'L2', 'label' => '手机号'],
            'email' => ['category' => 'person', 'level' => 'L2', 'label' => '邮箱'],
            'address' => ['category' => 'person', 'level' => 'L2', 'label' => '地址'],
            'name' => ['category' => 'person', 'level' => 'L2', 'label' => '姓名'],
            'ip_address' => ['category' => 'person', 'level' => 'L2', 'label' => 'IP 地址'],
        ];
    }

    // ═══════════════ M3-33b 增强：DPO / 未成年人 / 72h上报 ═══════════════

    /**
     * 获取 DPO 信息
     */
    public function getDpoInfo(): array
    {
        return [
            'name' => config('pipl.dpo.name'),
            'email' => config('pipl.dpo.email'),
            'phone' => config('pipl.dpo.phone'),
            'contact_info' => config('pipl.dpo.contact_info'),
        ];
    }

    /**
     * 更新 DPO 信息
     */
    public function updateDpoInfo(array $data): array
    {
        $allowed = ['name', 'email', 'phone', 'contact_info'];
        foreach ($data as $key => $value) {
            if (in_array($key, $allowed)) {
                // 更新 .env 或 config 缓存
                $envKey = 'PIPL_DPO_' . strtoupper($key);
                // 写入到持久化存储 — 使用 settings 表或直接改 config
                \Illuminate\Support\Facades\Cache::forever("pipl_dpo_{$key}", $value);
            }
        }
        return $this->getDpoInfo();
    }

    /**
     * 未成年人数据保护检查
     * 判断用户是否可能为未成年人，触发保护措施
     */
    public function checkMinorProtection(array $userData): array
    {
        $ageThreshold = config('pipl.minor.age_threshold', 14);
        $isMinor = false;
        $protectionMeasures = [];

        // 检查生日字段
        if (!empty($userData['birthday'])) {
            $birthday = \Carbon\Carbon::parse($userData['birthday']);
            $age = $birthday->age;
            if ($age < $ageThreshold) {
                $isMinor = true;
            }
        }

        // 如果标记为未成年人
        if ($isMinor) {
            $protectionMeasures = [
                'require_parental_consent' => true,
                'restricted_features' => config('pipl.minor.restricted_features', ['marketing', 'profiling', 'third_party_sharing']),
                'data_retention_days' => config('pipl.minor.data_retention_days', 180),
                'consent_required' => true,
            ];
        }

        return [
            'is_minor' => $isMinor,
            'age_threshold' => $ageThreshold,
            'protection_measures' => $protectionMeasures,
        ];
    }

    /**
     * 创建个人信息泄露事件（72小时上报）
     */
    public function createBreachNotification(array $data, int $userId): array
    {
        $hoursUntilDeadline = config('pipl.breach_notification.hours', 72);

        $breach = \App\Models\DataBreachNotification::create(array_merge($data, [
            'reporter_id' => $userId,
            'status' => 'reported',
            'notify_deadline' => now()->addHours($hoursUntilDeadline),
        ]));

        Log::warning('PIPL: 个人信息泄露上报', [
            'breach_id' => $breach->id,
            'type' => $data['type'] ?? 'unknown',
            'affected_count' => $data['affected_count'] ?? 0,
            'reporting_deadline' => $breach->notify_deadline,
        ]);

        return $breach->toArray();
    }

    /**
     * 检查是否有逾期未上报的泄露事件
     */
    public function getOverdueBreachNotifications(): array
    {
        $hours = config('pipl.breach_notification.hours', 72);
        $deadline = now()->subHours($hours);

        return \App\Models\DataBreachNotification::where('status', 'reported')
            ->where('created_at', '<=', $deadline)
            ->where(function ($q) {
                $q->whereNull('notified_at')
                  ->orWhere('notified_at', '<=', now()->subHours(72));
            })
            ->get()
            ->toArray();
    }

    /**
     * 获取 PIPL 增强统计（含DPO/未成年人/泄露）
     */
    public function getEnhancedStats(): array
    {
        $base = $this->getStats();

        $breachCount = \App\Models\DataBreachNotification::count();
        $openBreachCount = \App\Models\DataBreachNotification::whereIn('status', ['reported', 'investigating'])->count();

        return array_merge($base, [
            'dpo_configured' => !empty(config('pipl.dpo.name')),
            'minor_protection_enabled' => true,
            'breach_notifications' => [
                'total' => $breachCount,
                'open' => $openBreachCount,
                'overdue' => count($this->getOverdueBreachNotifications()),
            ],
        ]);
    }
}
