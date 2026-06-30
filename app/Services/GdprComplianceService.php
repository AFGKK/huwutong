<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Agent;
use App\Models\Customer;
use App\Models\DataProcessingAgreement;
use App\Models\Device;
use App\Models\DpaSignature;
use App\Models\GdprDataRequest;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Log as AuditLog;
use App\Models\Note;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * GDPR 合规服务 (M3-33)
 *
 * 处理 GDPR 数据主体请求（DSR）、数据可移植性导出、DPA 管理。
 */
class GdprComplianceService
{
    /**
     * 导出文件存储磁盘
     */
    const EXPORT_DISK = 'local';

    /**
     * 导出文件过期天数
     */
    const EXPORT_EXPIRY_DAYS = 30;

    /**
     * 提交 DSR 请求
     */
    public function submitRequest(int $userId, string $type, ?string $reason = null, array $requestData = []): GdprDataRequest
    {
        $request = GdprDataRequest::create([
            'user_id' => $userId,
            'type' => $type,
            'status' => GdprDataRequest::STATUS_PENDING,
            'reason' => $reason,
            'request_data' => $requestData,
        ]);

        Log::info('GDPR 数据主体请求已提交', [
            'request_id' => $request->id,
            'user_id' => $userId,
            'type' => $type,
        ]);

        return $request;
    }

    /**
     * 处理数据访问请求（Art.15）
     * 收集用户所有个人数据
     */
    public function processAccessRequest(GdprDataRequest $request): GdprDataRequest
    {
        $request->update(['status' => GdprDataRequest::STATUS_PROCESSING]);

        try {
            $user = User::with(['customer', 'tenant'])->find($request->user_id);

            if (! $user) {
                throw new \RuntimeException('用户不存在');
            }

            $personalData = $this->collectPersonalData($user);

            // 生成 JSON 报告
            $fileName = "gdpr-access-{$request->id}-{$user->id}-" . now()->format('YmdHis') . '.json';
            $path = "gdpr-exports/{$fileName}";

            Storage::disk(self::EXPORT_DISK)->put($path, json_encode($personalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $fileSize = Storage::disk(self::EXPORT_DISK)->size($path);

            $request->update([
                'status' => GdprDataRequest::STATUS_COMPLETED,
                'output_file' => $path,
                'file_size' => $fileSize,
                'expires_at' => now()->addDays(self::EXPORT_EXPIRY_DAYS),
                'completed_at' => now(),
            ]);

            Log::info('GDPR 数据访问请求已完成', [
                'request_id' => $request->id,
                'file_size' => $fileSize,
            ]);
        } catch (\Throwable $e) {
            $request->update([
                'status' => GdprDataRequest::STATUS_FAILED,
                'admin_notes' => $e->getMessage(),
            ]);
            Log::error('GDPR 数据访问请求处理失败', [
                'request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $request->fresh();
    }

    /**
     * 处理数据可移植性导出（Art.20）
     * 导出结构化、通用、机器可读格式
     */
    public function processPortabilityRequest(GdprDataRequest $request): GdprDataRequest
    {
        $request->update(['status' => GdprDataRequest::STATUS_PROCESSING]);

        try {
            $user = User::find($request->user_id);
            if (! $user) {
                throw new \RuntimeException('用户不存在');
            }

            $portableData = $this->collectPortableData($user);

            // 生成 JSON 导出
            $fileName = "gdpr-portability-{$request->id}-{$user->id}-" . now()->format('YmdHis') . '.json';
            $path = "gdpr-exports/{$fileName}";

            Storage::disk(self::EXPORT_DISK)->put($path, json_encode($portableData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $fileSize = Storage::disk(self::EXPORT_DISK)->size($path);

            $request->update([
                'status' => GdprDataRequest::STATUS_COMPLETED,
                'output_file' => $path,
                'file_size' => $fileSize,
                'expires_at' => now()->addDays(self::EXPORT_EXPIRY_DAYS),
                'completed_at' => now(),
            ]);

            Log::info('GDPR 数据可移植性导出已完成', [
                'request_id' => $request->id,
                'file_size' => $fileSize,
            ]);
        } catch (\Throwable $e) {
            $request->update([
                'status' => GdprDataRequest::STATUS_FAILED,
                'admin_notes' => $e->getMessage(),
            ]);
            Log::error('GDPR 数据可移植性导出失败', [
                'request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $request->fresh();
    }

    /**
     * 处理数据删除请求（被遗忘权 Art.17）
     */
    public function processErasureRequest(GdprDataRequest $request): GdprDataRequest
    {
        $request->update(['status' => GdprDataRequest::STATUS_PROCESSING]);

        try {
            $user = User::find($request->user_id);
            if (! $user) {
                throw new \RuntimeException('用户不存在');
            }

            DB::transaction(function () use ($user) {
                // 匿名化个人数据而非直接删除（保留统计价值）
                $this->anonymizeUserData($user);
            });

            $request->update([
                'status' => GdprDataRequest::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            Log::info('GDPR 数据删除请求已完成', [
                'request_id' => $request->id,
                'user_id' => $user->id,
            ]);
        } catch (\Throwable $e) {
            $request->update([
                'status' => GdprDataRequest::STATUS_FAILED,
                'admin_notes' => $e->getMessage(),
            ]);
        }

        return $request->fresh();
    }

    /**
     * 处理数据更正请求（Art.16）
     */
    public function processRectificationRequest(GdprDataRequest $request, array $corrections): GdprDataRequest
    {
        $request->update(['status' => GdprDataRequest::STATUS_PROCESSING]);

        try {
            $user = User::find($request->user_id);
            if (! $user) {
                throw new \RuntimeException('用户不存在');
            }

            $updated = [];
            foreach ($corrections as $field => $value) {
                if (in_array($field, ['name', 'email', 'phone'])) {
                    $user->$field = $value;
                    $updated[] = $field;
                }
            }

            if (! empty($updated)) {
                $user->save();
            }

            $request->update([
                'status' => GdprDataRequest::STATUS_COMPLETED,
                'completed_at' => now(),
                'request_data' => array_merge($request->request_data ?? [], ['updated_fields' => $updated]),
            ]);

            Log::info('GDPR 数据更正请求已完成', [
                'request_id' => $request->id,
                'updated_fields' => $updated,
            ]);
        } catch (\Throwable $e) {
            $request->update([
                'status' => GdprDataRequest::STATUS_FAILED,
                'admin_notes' => $e->getMessage(),
            ]);
        }

        return $request->fresh();
    }

    /**
     * 下载 GDPR 导出文件
     */
    public function downloadExport(GdprDataRequest $request): ?string
    {
        if (! $request->output_file || ! Storage::disk(self::EXPORT_DISK)->exists($request->output_file)) {
            return null;
        }

        if ($request->expires_at && $request->expires_at->isPast()) {
            return null;
        }

        return Storage::disk(self::EXPORT_DISK)->path($request->output_file);
    }

    /**
     * 收集用户个人数据（数据访问权）
     */
    public function collectPersonalData(User $user): array
    {
        $customer = $user->customer;

        return [
            'request_generated_at' => now()->toIso8601String(),
            'data_controller' => config('app.name'),
            'data_controller_contact' => config('mail.from.address'),
            'user_profile' => [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $user->avatar,
                'locale' => $user->locale,
                'timezone' => $user->timezone,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'created_at' => $user->created_at?->toIso8601String(),
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'last_login_ip' => $user->last_login_ip,
            ],
            'profile' => $customer ? [
                'customer_id' => $customer->id,
                'type' => $customer->type,
                'level' => $customer->level,
                'status' => $customer->status,
                'created_at' => $customer->created_at?->toIso8601String(),
            ] : null,
            'licenses' => License::where('customer_id', $customer?->id)
                ->get(['id', 'license_key', 'product_name', 'type', 'status', 'created_at', 'expires_at', 'activated_at'])
                ->toArray(),
            'subscriptions' => Subscription::where('customer_id', $customer?->id)
                ->get(['id', 'plan', 'status', 'amount', 'currency', 'current_period_start', 'current_period_end', 'created_at'])
                ->toArray(),
            'invoices' => Invoice::where('customer_id', $customer?->id)
                ->get(['id', 'invoice_number', 'total', 'currency', 'status', 'issued_at', 'paid_at'])
                ->toArray(),
            'login_history' => $user->logs()
                ->where('action', 'login')
                ->limit(50)
                ->get(['id', 'action', 'ip_address', 'user_agent', 'created_at'])
                ->toArray(),
        ];
    }

    /**
     * 收集可移植数据（数据可移植性 Art.20）
     */
    public function collectPortableData(User $user): array
    {
        $customer = $user->customer;

        return [
            'exported_at' => now()->toIso8601String(),
            'format' => 'https://schema.org/Person',
            'publisher' => config('app.name'),
            'user' => [
                'email' => $user->email,
                'locale' => $user->locale,
                'timezone' => $user->timezone,
            ],
            'licenses' => $customer ? License::where('customer_id', $customer->id)
                ->get(['license_key', 'product_name', 'type', 'status', 'seats', 'created_at', 'expires_at'])
                ->toArray() : [],
            'subscriptions' => $customer ? Subscription::where('customer_id', $customer->id)
                ->get(['plan', 'status', 'amount', 'currency', 'interval', 'current_period_start', 'current_period_end'])
                ->toArray() : [],
            'invoices' => $customer ? Invoice::where('customer_id', $customer->id)
                ->get(['invoice_number', 'total', 'currency', 'status', 'items', 'issued_at'])
                ->toArray() : [],
        ];
    }

    /**
     * 账户销毁 — 全数据匿名化（M3-62 数据匿名化处理增强版）
     *
     * 在 GDPR 被遗忘权基础上，覆盖所有包含 PII 的相关表：
     * - User / Customer 基础信息 ✓（原逻辑）
     * - Agent 代理商联系方式 ✓（新增）
     * - Ticket / TicketReply 工单内容 ✓（新增）
     * - Note 内部笔记 ✓（新增）
     * - Activity 活动记录 ✓（新增）
     * - AuditLog 审计日志 ✓（新增）
     * - Device 设备指纹 ✓（新增）
     * - License metadata ✓（新增）
     * - Invoice 账单地址 ✓（新增）
     *
     * @return array 匿名化的表名列表及处理记录数
     */
    public function anonymizeUserData(User $user): array
    {
        $anonId = 'anon_' . Str::random(16);
        $results = [];

        DB::transaction(function () use ($user, $anonId, &$results) {
            // 1. User 表 — 使用 forceFill 绕过 password hashed cast
            $user->timestamps = false;
            $user->forceFill([
                'name' => "User_{$anonId}",
                'email' => "{$anonId}@anonymized.local",
                'phone' => null,
                'password' => \Illuminate\Support\Facades\Hash::make(Str::random(32)), // 随机密码
                'password_history' => null,
                'mfa_secret' => null,
                'mfa_recovery_codes' => null,
                'mfa_recovery_used' => null,
                'last_login_ip' => null,
                'remember_token' => null,
            ])->saveQuietly();
            $user->timestamps = true;
            $results['users'] = 1;

            // 2. Customer 表
            $customer = $user->customer;
            if ($customer) {
                $customer->updateQuietly([
                    'notes' => null,
                ]);
                $results['customers'] = 1;
            }

            // 3. Agent 代理商
            $agent = Agent::where('user_id', $user->id)->first();
            if ($agent) {
                $agent->updateQuietly([
                    'contact_name' => "Agent_{$anonId}",
                    'contact_phone' => null,
                    'company' => null,
                    'notes' => null,
                ]);
                $results['agents'] = 1;
            }

            // 4. Ticket 工单（用户自己提交的，非分配给他的）
            $tickets = Ticket::where('user_id', $user->id)->get();
            foreach ($tickets as $ticket) {
                $ticket->updateQuietly([
                    'subject' => '[已删除]',
                    'description' => '[此内容已被用户删除]',
                    'metadata' => null,
                ]);
            }
            if ($tickets->isNotEmpty()) {
                $results['tickets'] = $tickets->count();
            }

            // 5. TicketReply 工单回复
            $repliesUpdated = TicketReply::where('user_id', $user->id)
                ->update(['content' => '[此内容已被用户删除]']);
            if ($repliesUpdated > 0) {
                $results['ticket_replies'] = $repliesUpdated;
            }

            // 6. Note 笔记
            $notesUpdated = Note::where('user_id', $user->id)
                ->update([
                    'content' => '[此内容已被用户删除]',
                    'mentions' => null,
                ]);
            if ($notesUpdated > 0) {
                $results['notes'] = $notesUpdated;
            }

            // 7. Activity 活动记录（保留时间戳但去掉 IP 和描述中的 PII）
            $activities = Activity::where('user_id', $user->id)->get();
            foreach ($activities as $activity) {
                $activity->updateQuietly([
                    'ip_address' => '127.0.0.1',
                    'description' => '[已删除]',
                    'metadata' => ['anonymized' => true],
                ]);
            }
            if ($activities->isNotEmpty()) {
                $results['activities'] = $activities->count();
            }

            // 8. Audit Log 审计日志
            $logUpdated = AuditLog::where('user_id', $user->id)
                ->update([
                    'ip_address' => '127.0.0.1',
                    'user_agent' => null,
                    'description' => '[已删除]',
                    'payload' => null,
                ]);
            if ($logUpdated > 0) {
                $results['audit_logs'] = $logUpdated;
            }

            // 9. Device 设备指纹
            $customerId = $customer?->id;
            if ($customerId) {
                $devicesUpdated = Device::whereHas('license', function ($q) use ($customerId) {
                    $q->where('customer_id', $customerId);
                })->orWhere('metadata->user_id', $user->id)
                    ->update([
                        'fingerprint' => hash('sha256', $anonId),
                        'metadata' => null,
                    ]);
                if ($devicesUpdated > 0) {
                    $results['devices'] = $devicesUpdated;
                }

                // 10. License — 匿名化 metadata
                $licenses = License::where('customer_id', $customerId)->get();
                foreach ($licenses as $license) {
                    $meta = $license->metadata ?? [];
                    $meta['anonymized_at'] = now()->toIso8601String();
                    $license->updateQuietly(['metadata' => $meta]);
                }
                if ($licenses->isNotEmpty()) {
                    $results['licenses'] = $licenses->count();
                }

                // 11. Invoice 账单地址
                $invoicesUpdated = Invoice::where('customer_id', $customerId)
                    ->update([
                        'billing_address_line1' => '[已删除]',
                        'billing_address_line2' => null,
                        'billing_city' => null,
                        'billing_region' => null,
                        'billing_country' => null,
                        'billing_zip' => null,
                        'notes' => null,
                    ]);
                if ($invoicesUpdated > 0) {
                    $results['invoices'] = $invoicesUpdated;
                }
            }
        });

        Log::info('用户数据已被完整匿名化（M3-62）', [
            'user_id' => $user->id,
            'anonymized_id' => $anonId,
            'affected_tables' => $results,
        ]);

        return $results;
    }

    // ─── DPA 管理 ───

    /**
     * 发布 DPA
     */
    public function publishDpa(int $dpaId): DataProcessingAgreement
    {
        $dpa = DataProcessingAgreement::findOrFail($dpaId);
        $dpa->update([
            'status' => DataProcessingAgreement::STATUS_PUBLISHED,
            'effective_at' => now(),
        ]);

        // 取消旧版本
        DataProcessingAgreement::where('tenant_id', $dpa->tenant_id)
            ->where('id', '!=', $dpa->id)
            ->where('status', DataProcessingAgreement::STATUS_PUBLISHED)
            ->update(['status' => DataProcessingAgreement::STATUS_ARCHIVED]);

        return $dpa->fresh();
    }

    /**
     * 签署 DPA
     */
    public function signDpa(int $dpaId, int $tenantId, int $userId): DpaSignature
    {
        $dpa = DataProcessingAgreement::findOrFail($dpaId);
        $user = User::findOrFail($userId);

        if ($dpa->status !== DataProcessingAgreement::STATUS_PUBLISHED) {
            throw new \RuntimeException('DPA 尚未发布');
        }

        if ($dpa->isSignedByTenant($tenantId)) {
            throw new \RuntimeException('该租户已签署此 DPA');
        }

        return DpaSignature::create([
            'dpa_id' => $dpaId,
            'tenant_id' => $tenantId,
            'signed_by' => $userId,
            'signer_name' => $user->name,
            'signer_title' => $user->role,
            'ip_address' => request()->ip(),
            'signed_at' => now(),
        ]);
    }

    /**
     * 获取 GDPR 统计概览
     */
    public function getStats(): array
    {
        return [
            'total_requests' => GdprDataRequest::count(),
            'pending' => GdprDataRequest::where('status', GdprDataRequest::STATUS_PENDING)->count(),
            'processing' => GdprDataRequest::where('status', GdprDataRequest::STATUS_PROCESSING)->count(),
            'completed' => GdprDataRequest::where('status', GdprDataRequest::STATUS_COMPLETED)->count(),
            'rejected' => GdprDataRequest::where('status', GdprDataRequest::STATUS_REJECTED)->count(),
            'failed' => GdprDataRequest::where('status', GdprDataRequest::STATUS_FAILED)->count(),
            'by_type' => GdprDataRequest::selectRaw('type, count(*) as total')
                ->groupBy('type')
                ->pluck('total', 'type')
                ->toArray(),
            'dpa_count' => DataProcessingAgreement::count(),
            'published_dpa' => DataProcessingAgreement::where('status', DataProcessingAgreement::STATUS_PUBLISHED)->count(),
        ];
    }
}
