<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\LicenseAnalyticsEvent;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class MetricsController extends Controller
{
    /**
     * Prometheus 指标端点
     *
     * GET /metrics
     */
    public function index(): Response
    {
        $metrics = $this->buildMetrics();

        return response($metrics, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }

    protected function buildMetrics(): string
    {
        $metrics = [];

        // ─── HELP / TYPE ───
        $metrics[] = '# HELP huwutong_info 互物通 License 管理系统信息';
        $metrics[] = '# TYPE huwutong_info gauge';
        $metrics[] = 'huwutong_info{version="' . config('app.version', '1.0') . '",env="' . app()->environment() . '"} 1';

        // ─── License 统计 ───
        $metrics[] = '';
        $metrics[] = '# HELP license_active_count 当前活跃License数量';
        $metrics[] = '# TYPE license_active_count gauge';
        $metrics[] = 'license_active_count ' . Cache::remember('metric:active_licenses', 60, function () {
            return License::where('status', 'active')->count();
        });

        $metrics[] = '# HELP license_total_count License总数';
        $metrics[] = '# TYPE license_total_count gauge';
        $metrics[] = 'license_total_count ' . License::count();

        $metrics[] = '# HELP license_expiring_soon 30天内到期License数';
        $metrics[] = '# TYPE license_expiring_soon gauge';
        $metrics[] = 'license_expiring_soon ' . License::where('status', 'active')
            ->where('expires_at', '<=', now()->addDays(30))
            ->where('expires_at', '>', now())
            ->count();

        // ─── 激活统计 ───
        $metrics[] = '';
        $metrics[] = '# HELP license_activations_total License激活次数';
        $metrics[] = '# TYPE license_activations_total counter';
        $metrics[] = 'license_activations_total ' . LicenseActivation::count();

        $metrics[] = '# HELP license_activations_today 今日激活次数';
        $metrics[] = '# TYPE license_activations_today gauge';
        $metrics[] = 'license_activations_today ' . LicenseActivation::whereDate('activated_at', today())->count();

        // ─── 违规统计 ───
        $metrics[] = '';
        $metrics[] = '# HELP license_validation_total License验证次数';
        $metrics[] = '# TYPE license_validation_total counter';
        $metrics[] = 'license_validation_total ' . Cache::remember('metric:validations', 60, function () {
            return LicenseAnalyticsEvent::where('event_type', 'activation')->count();
        });

        $metrics[] = '# HELP license_validation_failures_total License验证失败次数';
        $metrics[] = '# TYPE license_validation_failures_total counter';
        $metrics[] = 'license_validation_failures_total ' . LicenseAnalyticsEvent::where('event_type', 'violation')->count();

        // ─── 用户统计 ───
        $metrics[] = '';
        $metrics[] = '# HELP huwutong_users_total 用户总数';
        $metrics[] = '# TYPE huwutong_users_total gauge';
        $metrics[] = 'huwutong_users_total ' . \App\Models\User::count();

        // ─── 队列统计 ───
        $metrics[] = '';
        $metrics[] = '# HELP huwutong_queue_failed_jobs 失败Job数';
        $metrics[] = '# TYPE huwutong_queue_failed_jobs gauge';
        try {
            $failedCount = \DB::table('failed_jobs')->count();
            $metrics[] = 'huwutong_queue_failed_jobs ' . $failedCount;
        } catch (\Exception) {
            $metrics[] = 'huwutong_queue_failed_jobs 0';
        }

        return implode("\n", $metrics) . "\n";
    }
}
