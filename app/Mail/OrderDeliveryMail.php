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
            subject: __('app.mail.order_delivery_subject', ['app' => config('app.name'), 'no' => $this->order->order_no]),
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
            $expiry = isset($lic['expires_at']) ? __('app.mail.license_expires', ['date' => $lic['expires_at']]) : __('app.mail.license_permanent');
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

                $orderShippedLabel = __('app.mail.order_shipped_title');
        $orderPrefixLabel = __('app.mail.order_no_prefix');
        $orderGreetingLabel = __('app.mail.order_auto_processed');
        $productLabel2 = __('app.mail.product');
        $quantityLabel2 = __('app.mail.quantity');
        $amountLabelOrd = __('app.mail.amount_label');
        $totalLabel2 = __('app.mail.invoice_total');
        $licenseInfoLabel = __('app.mail.license_info');
        $validityLabel = __('app.mail.validity_period');
        $saveWarningLabel = __('app.mail.license_save_warning');
        $autoDeliveryLabel = __('app.mail.auto_delivery_system');
return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8"></head>
        <body style="font-family:'Helvetica Neue',Arial,sans-serif;padding:20px;background:#f5f5f5">
            <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden">
                <div style="background:#0f172a;color:#fff;padding:24px;text-align:center">
                    <h2 style="margin:0">{$orderShippedLabel}</h2>
                    <p style="margin:8px 0 0;opacity:0.9">{$orderPrefixLabel}{$order->order_no}</p>
                </div>
                <div style="padding:24px">
                    <p>{$orderGreetingLabel}</p>

                    <table style="width:100%;border-collapse:collapse;margin:16px 0">
                        <thead><tr style="background:#fafafa">
                            <th style="padding:8px;border:1px solid #e0e0e0;text-align:left">{$productLabel2}</th>
                            <th style="padding:8px;border:1px solid #e0e0e0">{$quantityLabel2}</th>
                            <th style="padding:8px;border:1px solid #e0e0e0">{$amountLabelOrd}</th>
                        </tr></thead>
                        <tbody>{$itemRows}</tbody>
                    </table>

                    <p style="font-size:16px;font-weight:600">{$totalLabel2}¥{$order->final_amount}</p>

                    <h3 style="margin:24px 0 12px">{$licenseInfoLabel}</h3>
                    <table style="width:100%;border-collapse:collapse">
                        <thead><tr style="background:#fafafa">
                            <th style="padding:8px;border:1px solid #e0e0e0;text-align:left">License Key</th>
                            <th style="padding:8px;border:1px solid #e0e0e0">{$validityLabel}</th>
                        </tr></thead>
                        <tbody>{$licenseRows}</tbody>
                    </table>

                    <div style="margin-top:24px;padding:16px;background:#f0f9ff;border-radius:4px">
                        <p style="margin:0;font-size:13px;color:#606266">
                            {$saveWarningLabel}
                        </p>
                    </div>
                </div>
                <div style="padding:16px;text-align:center;color:#909399;font-size:12px;border-top:1px solid #f0f0f0">
                    <p style="margin:0">{$appName} · {$autoDeliveryLabel}</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }
}
