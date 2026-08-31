<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CustomerSmtpConfig;
use App\Services\CustomerSmtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerSmtpController extends Controller
{
    public function __construct(protected CustomerSmtpService $smtpService) {}

    /**
     * 仪表盘
     */
    public function dashboard(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->smtpService->getDashboard($request->user()->tenant_id)
        );
    }

    /**
     * 配置列表
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->smtpService->getConfigs($request->user()->tenant_id)
        );
    }

    /**
     * 创建配置
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => 'required|string|max:50',
            'name' => 'nullable|string|max:100',
            'host' => 'required|string|max:200',
            'port' => 'required|integer|min:1|max:65535',
            'encryption' => 'nullable|string|in:tls,ssl,null',
            'username' => 'nullable|string|max:200',
            'password' => 'nullable|string|max:500',
            'from_address' => 'nullable|email|max:200',
            'from_name' => 'nullable|string|max:100',
            'is_primary' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.smtp.validation_failed'), $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $data['tenant_id'] = $request->user()->tenant_id;

        $config = $this->smtpService->create($data);
        return ApiResponse::created($config, __('app.api.smtp.config_created'));
    }

    /**
     * 更新配置
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $config = CustomerSmtpConfig::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'provider' => 'sometimes|string|max:50',
            'name' => 'nullable|string|max:100',
            'host' => 'sometimes|string|max:200',
            'port' => 'sometimes|integer|min:1|max:65535',
            'encryption' => 'nullable|string|in:tls,ssl,null',
            'username' => 'nullable|string|max:200',
            'password' => 'nullable|string|max:500',
            'from_address' => 'nullable|email|max:200',
            'from_name' => 'nullable|string|max:100',
            'is_primary' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.smtp.validation_failed'), $validator->errors()->toArray());
        }

        $config = $this->smtpService->update($config, $validator->validated());
        return ApiResponse::success($config, __('app.api.smtp.config_updated'));
    }

    /**
     * 删除配置
     */
    public function destroy(int $id): JsonResponse
    {
        $config = CustomerSmtpConfig::findOrFail($id);
        $config->logs()->delete();
        $config->delete();
        return ApiResponse::success(null, __('app.api.smtp.config_deleted'));
    }

    /**
     * 测试连接
     */
    public function test(int $id): JsonResponse
    {
        $config = CustomerSmtpConfig::findOrFail($id);
        $result = $this->smtpService->testConnection($config);
        return $result['success']
            ? ApiResponse::success($result, __('app.api.smtp.connection_ok'))
            : ApiResponse::error($result['message'], 400);
    }

    /**
     * 设置为主 SMTP
     */
    public function setPrimary(int $id): JsonResponse
    {
        $config = CustomerSmtpConfig::findOrFail($id);
        $this->smtpService->update($config, ['is_primary' => true]);
        return ApiResponse::success(null, __('app.api.smtp.set_primary'));
    }

    /**
     * 发送测试邮件
     */
    public function sendTest(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'to' => 'required|email',
        ]);
        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.smtp.validation_failed'), $validator->errors()->toArray());
        }

        $config = CustomerSmtpConfig::findOrFail($id);
        $result = $this->smtpService->send(
            $request->input('to'),
            __('app.api.smtp.test_subject'),
            __('app.api.smtp.test_body', ['time' => now()]),
            $config->tenant_id
        );

        return $result['success']
            ? ApiResponse::success(null, __('app.api.smtp.test_sent'))
            : ApiResponse::error($result['message'], 400);
    }

    /**
     * 发送日志
     */
    public function logs(Request $request): JsonResponse
    {
        return ApiResponse::success($this->smtpService->getLogs($request->all()));
    }

    /**
     * 手动恢复检查
     */
    public function recover(): JsonResponse
    {
        $recovered = $this->smtpService->checkAndRecover();
        return ApiResponse::success(['recovered' => $recovered], __('app.api.smtp.configs_recovered', ['count' => count($recovered)]));
    }

    /**
     * 提供商列表
     */
    public function providers(): JsonResponse
    {
        return ApiResponse::success($this->smtpService->getProviders());
    }

    // ─── SMTP 降级配置 (M2-84) ───

    public function getFallbackConfig(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        return ApiResponse::success([
            'failure_threshold' => (int) config('customer-smtp.fallback.failure_threshold', 3),
            'recovery_interval' => (int) config('customer-smtp.fallback.recovery_interval', 30),
            'notify_on_fallback' => config('customer-smtp.fallback.notify_on_fallback', true),
            'notify_on_recovery' => config('customer-smtp.fallback.notify_on_recovery', true),
            'notify_emails' => config('customer-smtp.fallback.notify_emails', []),
            'auto_recover' => config('customer-smtp.fallback.auto_recover', true),
            'current_status' => $this->getCurrentFallbackStatus($tenantId),
        ]);
    }

    public function updateFallbackConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'failure_threshold' => 'nullable|integer|min:1|max:20',
            'recovery_interval' => 'nullable|integer|min:5|max:1440',
            'notify_on_fallback' => 'nullable|boolean',
            'notify_on_recovery' => 'nullable|boolean',
            'notify_emails' => 'nullable|array',
            'notify_emails.*' => 'email',
            'auto_recover' => 'nullable|boolean',
        ]);

        $config = config('customer-smtp.fallback');
        foreach ($validated as $key => $value) {
            $config[$key] = $value;
        }

        // 写入配置文件（生产环境应使用数据库存储）
        config(['customer-smtp.fallback' => $config]);

        // 持久化到缓存
        $tenantId = $request->user()->tenant_id;
        \Illuminate\Support\Facades\Cache::forever("smtp_fallback_config_{$tenantId}", $config);

        return ApiResponse::success($config, __('app.api.smtp.fallback_updated'));
    }

    public function testFallback(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        try {
            $result = $this->smtpService->testFallbackChain($tenantId);
            return ApiResponse::success($result);
        } catch (\Throwable $e) {
            return ApiResponse::error(__('app.api.smtp.fallback_test_failed', ['error' => $e->getMessage()]));
        }
    }

    protected function getCurrentFallbackStatus(?int $tenantId): array
    {
        $primaryHealthy = true;
        $backupHealthy = true;
        $currentlyUsing = 'primary';

        // 从缓存读取当前状态
        if ($tenantId) {
            $status = \Illuminate\Support\Facades\Cache::get("smtp_fallback_status_{$tenantId}");
            if ($status) {
                $primaryHealthy = $status['primary_healthy'] ?? true;
                $backupHealthy = $status['backup_healthy'] ?? true;
                $currentlyUsing = $status['currently_using'] ?? 'primary';
            }
        }

        return [
            'primary_healthy' => $primaryHealthy,
            'backup_healthy' => $backupHealthy,
            'currently_using' => $currentlyUsing,
            'last_fallback_at' => \Illuminate\Support\Facades\Cache::get("smtp_last_fallback_{$tenantId}"),
            'last_recovery_at' => \Illuminate\Support\Facades\Cache::get("smtp_last_recovery_{$tenantId}"),
        ];
    }
}
