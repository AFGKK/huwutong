<?php

namespace App\Services;

use App\Mail\DemoBookingConfirmation;
use App\Mail\DemoBookingNotification;
use App\Models\DemoBooking;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * 预约Demo/联系销售服务 (M2-98)
 */
class DemoBookingService
{
    /**
     * 提交预约请求
     */
    public function submit(array $data): array
    {
        // 验证 Honeypot
        $honeypotField = config('demo-booking.form.honeypot', 'website_url');
        if (!empty($data[$honeypotField])) {
            return ['success' => true, 'message' => '预约请求已提交'];
        }

        $booking = DemoBooking::create([
            'company_name' => $data['company_name'],
            'contact_name' => $data['contact_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'employee_count' => $data['employee_count'] ?? null,
            'product_interest' => $data['product_interest'] ?? null,
            'message' => $data['message'] ?? null,
            'source' => $data['source'] ?? 'website',
            'status' => 'pending',
        ]);

        $this->sendConfirmation($booking);
        $this->notifyAdmin($booking);

        return [
            'success' => true,
            'message' => '预约请求已提交，我们的销售团队将在24小时内与您联系',
            'booking' => $booking->toArray(),
        ];
    }

    /**
     * 发送确认邮件给客户
     */
    protected function sendConfirmation(DemoBooking $booking): void
    {
        try {
            Mail::to($booking->email)->send(new DemoBookingConfirmation($booking));
        } catch (\Throwable $e) {
            Log::error('Demo预约确认邮件失败', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 通知管理员
     */
    protected function notifyAdmin(DemoBooking $booking): void
    {
        $adminEmail = config('demo-booking.notifications.admin_email', 'sales@huwutong.com');

        try {
            Mail::to($adminEmail)->send(new DemoBookingNotification($booking));

            Log::info('新Demo预约', [
                'id' => $booking->id,
                'company' => $booking->company_name,
                'email' => $booking->email,
            ]);
        } catch (\Throwable $e) {
            Log::error('Demo预约通知失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 获取预约列表
     */
    public function getList(array $filters = [], int $perPage = 20): array
    {
        $query = DemoBooking::orderByDesc('created_at');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($perPage)->toArray();
    }

    /**
     * 更新预约状态
     */
    public function updateStatus(int $id, string $status): array
    {
        $booking = DemoBooking::findOrFail($id);
        $booking->update(['status' => $status]);

        if ($status === 'contacted' && !$booking->contacted_at) {
            $booking->update(['contacted_at' => now()]);
        }

        return ['success' => true, 'message' => '状态已更新', 'booking' => $booking->fresh()->toArray()];
    }

    /**
     * 获取统计
     */
    public function getStats(): array
    {
        $stats = DemoBooking::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'contacted' THEN 1 ELSE 0 END) as contacted,
            SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted,
            SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) as lost
        ")->first();

        $total = $stats->total ?? 0;
        $converted = $stats->converted ?? 0;

        return [
            'total' => (int) $total,
            'pending' => (int) ($stats->pending ?? 0),
            'contacted' => (int) ($stats->contacted ?? 0),
            'scheduled' => (int) ($stats->scheduled ?? 0),
            'completed' => (int) ($stats->completed ?? 0),
            'converted' => (int) $converted,
            'lost' => (int) ($stats->lost ?? 0),
            'conversion_rate' => $total > 0 ? round(($converted / $total) * 100, 1) : 0,
        ];
    }

    /**
     * 获取 Calendly 预约链接
     */
    public function getCalendlyLink(): ?string
    {
        if (!config('demo-booking.calendly.enabled')) {
            return null;
        }
        return config('demo-booking.calendly.organization_url');
    }
}
