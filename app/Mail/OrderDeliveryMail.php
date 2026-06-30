<?php

namespace App\Mail;

use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderDeliveryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public Delivery $delivery,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[' . config('app.name') . '] 订单 #' . $this->order->order_no . ' 已发货',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtml(),
        );
    }

    protected function buildHtml(): string
    {
        $order = $this->order;
        $delivery = $this->delivery;
        $appName = config('app.name');
        $content = json_decode($delivery->content, true) ?: [];
        $licenses = $content['licenses'] ?? [];

        $licenseRows = '';
        foreach ($licenses as $lic) {
            $expiry = isset($lic['expires_at']) ? "到期: {$lic['expires_at']}" : '永久有效';
            $licenseRows .= <<<HTML
            <tr>
                <td style="padding:8px;border:1px solid #e0e0e0;font-family:monospace">{$lic['license_key']}</td>
                <td style="padding:8px;border:1px solid #e0e0e0">{$expiry}</td>
            </tr>
            HTML;
        }

        $itemRows = '';
        foreach ($order->items as $item) {
            $itemRows .= "<tr><td style='padding:6px;border:1px solid #e0e0e0'>{$item->name}</td>
                <td style='padding:6px;border:1px solid #e0e0e0'>{$item->quantity}</td>
                <td style='padding:6px;border:1px solid #e0e0e0'>¥{$item->subtotal}</td></tr>";
        }

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8"></head>
        <body style="font-family:'Helvetica Neue',Arial,sans-serif;padding:20px;background:#f5f5f5">
            <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden">
                <div style="background:#409eff;color:#fff;padding:24px;text-align:center">
                    <h2 style="margin:0">订单已发货</h2>
                    <p style="margin:8px 0 0;opacity:0.9">订单 #{$order->order_no}</p>
                </div>
                <div style="padding:24px">
                    <p>您好，您的订单已自动处理完成！</p>

                    <table style="width:100%;border-collapse:collapse;margin:16px 0">
                        <thead><tr style="background:#fafafa">
                            <th style="padding:8px;border:1px solid #e0e0e0;text-align:left">商品</th>
                            <th style="padding:8px;border:1px solid #e0e0e0">数量</th>
                            <th style="padding:8px;border:1px solid #e0e0e0">金额</th>
                        </tr></thead>
                        <tbody>{$itemRows}</tbody>
                    </table>

                    <p style="font-size:16px;font-weight:600">合计：¥{$order->final_amount}</p>

                    <h3 style="margin:24px 0 12px">License 信息</h3>
                    <table style="width:100%;border-collapse:collapse">
                        <thead><tr style="background:#fafafa">
                            <th style="padding:8px;border:1px solid #e0e0e0;text-align:left">License Key</th>
                            <th style="padding:8px;border:1px solid #e0e0e0">有效期</th>
                        </tr></thead>
                        <tbody>{$licenseRows}</tbody>
                    </table>

                    <div style="margin-top:24px;padding:16px;background:#f0f9ff;border-radius:4px">
                        <p style="margin:0;font-size:13px;color:#606266">
                            请妥善保存您的 License Key。如非本人操作，请立即联系我们。
                        </p>
                    </div>
                </div>
                <div style="padding:16px;text-align:center;color:#909399;font-size:12px;border-top:1px solid #f0f0f0">
                    <p style="margin:0">{$appName} · 自动发货系统</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }
}
