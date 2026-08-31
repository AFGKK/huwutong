<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * 佣金通知邮件 M2-128
 *
 * 为收益通知提供更丰富的邮件模板，
 * 支持金额高亮、操作按钮、统计表格等。
 */
class CommissionNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $title,
        public string $content,
        public ?array $payload = null,
        public ?string $userName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->title . ' - HWT License',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtml(),
        );
    }

    private function buildHtml(): string
    {
        $greeting = $this->userName ? __('app.mail.greeting_name', ['name' => $this->userName]) : __('app.mail.greeting');
        $actionUrl = $this->payload['action_url'] ?? null;
        $actionText = $this->payload['action_text'] ?? null;

        // 解析内容中的统计信息
        $contentLines = explode("\n", $this->content);
        $hasStats = count($contentLines) > 1;

        $statsHtml = '';
        if ($hasStats) {
            $statsHtml = '<table style="width: 100%; border-collapse: collapse; margin: 16px 0;">';
            foreach ($contentLines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }
                // 检测是否为统计数据行（含 •）
                if (str_starts_with($line, '•') || str_starts_with($line, '-')) {
                    $statsHtml .= '<tr style="border-bottom: 1px solid #f0f0f0;">'
                        . '<td style="padding: 8px 12px; color: #555; font-size: 14px;">'
                        . htmlspecialchars($line) . '</td></tr>';
                }
            }
            $statsHtml .= '</table>';
        }

        // 提取金额用于高亮显示
        $amountHighlight = '';
        if ($this->payload && isset($this->payload['amount'])) {
            $amount = number_format((float) $this->payload['amount'], 2);
            $amountHighlight = <<<HTML
            <div style="text-align: center; margin: 24px 0;">
                <div style="font-size: 13px; color: #999; margin-bottom: 4px;">{$amountLabel2}</div>
                <div style="font-size: 32px; font-weight: 700; color: #0f172a;">¥{$amount}</div>
            </div>
            HTML;
        }

        // 提取类型图标
        $typeIcon = '';
        $typeLabel = '';
        if ($this->payload && isset($this->payload['type'])) {
            $typeIcons = [
                'commission_credited' => ['💰', __('app.mail.commission_credited')],
                'commission_released' => ['🔓', __('app.mail.commission_released')],
                'payout_status' => ['💸', __('app.mail.payout_status')],
                'monthly_report' => ['📊', __('app.mail.monthly_report')],
                'threshold_reached' => ['🏆', __('app.mail.threshold_reached')],
                'negative_balance' => ['⚠️', __('app.mail.negative_balance')],
            ];
            $typeInfo = $typeIcons[$this->payload['type']] ?? ['📬', __('app.mail.notification_fallback')];
            $typeIcon = $typeInfo[0];
            $typeLabel = $typeInfo[1];
        }

                $amountLabel2 = __('app.mail.amount_label');
return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif; padding: 40px; background: #f0f2f5;">
    <div style="max-width: 600px; margin: 0 auto;">
        <!-- Header -->
        <div style="text-align: center; padding: 16px 0;">
            <span style="font-size: 20px; font-weight: 700; color: #0f172a;">HWT License</span>
        </div>

        <!-- Body -->
        <div style="background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.06);">
            <!-- Type Banner -->
            <div style="background: linear-gradient(135deg, #0f172a, #1e293b); padding: 24px 32px; text-align: center;">
                <div style="font-size: 36px; margin-bottom: 8px;">{$typeIcon}</div>
                <div style="font-size: 18px; color: #fff; font-weight: 600;">{$typeLabel}</div>
            </div>

            <!-- Content -->
            <div style="padding: 32px;">
                <h2 style="margin: 0 0 16px; color: #1a1a1a; font-size: 20px;">{$this->title}</h2>
                <p style="color: #666; line-height: 1.6; margin: 0 0 8px;">{$greeting}</p>

                {$amountHighlight}

                <div style="color: #333; line-height: 1.8; font-size: 14px;">
                    {$statsHtml}
                </div>

                <!-- Action Button -->
HTML
            . ($actionUrl ? <<<HTML
                <div style="text-align: center; margin: 24px 0;">
                    <a href="{$actionUrl}" style="display: inline-block; padding: 12px 32px; background: #0f172a; color: #fff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 500;">{$actionText}</a>
                </div>
HTML
            : '')
            . <<<HTML
            </div>
        </div>

        <!-- Footer -->
        <div style="text-align: center; padding: 24px 16px;">
            <p style="color: #999; font-size: 12px; margin: 0;">__('app.mail.auto_send_footer')</p>
            <p style="color: #999; font-size: 12px; margin: 4px 0 0;">__('app.mail.notification_prefs_hint')</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    public function attachments(): array
    {
        return [];
    }
}
