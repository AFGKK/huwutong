<?php

namespace App\Console\Commands;

use App\Models\EmailDripRecipient;
use App\Services\EmailDripService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailDripSendCommand extends Command
{
    protected $signature = 'email-drip:send {--batch=100 : 每批发送数量} {--campaign= : 指定活动ID(可选)}';

    protected $description = '发送待处理的 Drip 邮件序列';

    public function handle(EmailDripService $dripService): int
    {
        $batchSize = (int) $this->option('batch');
        $campaignId = $this->option('campaign');
        $now = Carbon::now();

        // 静默时段不发送
        $quietStart = config('email-drip.sending.quiet_hours_start', '22:00');
        $quietEnd = config('email-drip.sending.quiet_hours_end', '08:00');
        $currentTime = $now->format('H:i');

        // 跨天静默检查：22:00~08:00
        $inQuietHours = false;
        if ($quietStart < $quietEnd) {
            // 同天范围
            $inQuietHours = $currentTime >= $quietStart && $currentTime < $quietEnd;
        } else {
            // 跨天范围
            $inQuietHours = $currentTime >= $quietStart || $currentTime < $quietEnd;
        }

        if ($inQuietHours) {
            $this->info("当前处于静默时段 ({$quietStart}~{$quietEnd})，跳过发送");
            return Command::SUCCESS;
        }

        // 查询待发送的 recipient
        $query = EmailDripRecipient::where('status', 'pending')
            ->whereHas('sequence', fn($q) => $q->where('is_active', true))
            ->whereHas('campaign', fn($q) => $q->where('status', 'active'));

        if ($campaignId) {
            $query->where('campaign_id', $campaignId);
        }

        // 计算发送时间：创建时间 + delay_days
        // 使用子查询关联 sequences.delay_days
        $pending = $query
            ->whereHas('sequence', function ($q) use ($now) {
                $q->whereRaw(
                    'DATE_ADD(email_drip_recipients.created_at, INTERVAL email_drip_sequences.delay_days DAY) <= ?',
                    [$now]
                );
            })
            ->with(['sequence', 'campaign', 'customer'])
            ->limit($batchSize)
            ->get();

        if ($pending->isEmpty()) {
            $this->info('没有待发送的 Drip 邮件');
            return Command::SUCCESS;
        }

        $sent = 0;
        $failed = 0;

        foreach ($pending as $recipient) {
            try {
                $sequence = $recipient->sequence;
                $customer = $recipient->customer;

                if (!$customer || !$recipient->email) {
                    $recipient->update(['status' => 'failed', 'error_message' => '客户或邮箱不存在']);
                    $failed++;
                    continue;
                }

                // 替换模板变量
                $subject = $this->replaceVariables($sequence->subject, $customer);
                $content = $this->replaceVariables($sequence->content, $customer);

                // 追踪像素
                $trackingPixel = '';
                if (config('email-drip.tracking.track_opens', true)) {
                    $pixelUrl = url("/api/email-drip/track-open/{$recipient->id}");
                    $trackingPixel = "<img src=\"{$pixelUrl}\" width=\"1\" height=\"1\" style=\"display:none\" alt=\"\" />";
                }

                // 链接重定向追踪
                if (config('email-drip.tracking.track_clicks', true)) {
                    $content = $this->wrapLinks($content, $recipient->id);
                }

                $content .= $trackingPixel;

                // 发送邮件
                Mail::html($content, function ($message) use ($recipient, $subject) {
                    $message->to($recipient->email)
                        ->subject($subject)
                        ->from(config('mail.from.address'), config('mail.from.name'));
                });

                $recipient->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);

                $sent++;

                $this->line("  ✅ 已发送: {$recipient->email} - {$subject}");
            } catch (\Exception $e) {
                $recipient->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
                $failed++;
                Log::error('Drip发送失败', [
                    'recipient_id' => $recipient->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("  ❌ 发送失败: {$recipient->email} - {$e->getMessage()}");
            }
        }

        $this->info("Drip 发送完成: {$sent} 成功, {$failed} 失败");
        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function replaceVariables(string $text, $customer): string
    {
        $replacements = [
            '{{customer_name}}' => $customer?->name ?? '客户',
            '{{customer_email}}' => $customer?->email ?? '',
            '{{site_name}}' => config('app.name', '互物通'),
            '{{current_year}}' => date('Y'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    private function wrapLinks(string $content, int $recipientId): string
    {
        // 将 HTML 中的 href 链接替换为追踪链接
        return preg_replace_callback(
            '/<a\s+([^>]*?)href=["\'](https?:\/\/[^"\']+)["\']([^>]*?)>/i',
            function ($matches) use ($recipientId) {
                $url = url("/api/email-drip/track-click/{$recipientId}?url=" . urlencode($matches[2]));
                return '<a ' . $matches[1] . 'href="' . $url . '" ' . $matches[3] . '>';
            },
            $content
        );
    }
}
