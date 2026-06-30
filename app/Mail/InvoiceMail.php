<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[' . config('app.name') . '] 电子发票 - ' . $this->invoice->invoice_no,
        );
    }

    public function content(): Content
    {
        $invoice = $this->invoice;
        $items = $invoice->lineItems;

        $rows = '';
        foreach ($items as $item) {
            $rows .= "<tr><td style='padding:8px;border:1px solid #ddd'>{$item->description}</td>
                <td style='padding:8px;border:1px solid #ddd;text-align:center'>{$item->quantity}</td>
                <td style='padding:8px;border:1px solid #ddd;text-align:right'>¥{$item->subtotal}</td></tr>";
        }

        $html = <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8"></head>
        <body style="font-family:'Helvetica Neue',Arial,sans-serif;padding:20px;background:#f5f5f5">
            <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden">
                <div style="background:#409eff;color:#fff;padding:24px;text-align:center">
                    <h2 style="margin:0">电子发票</h2>
                    <p style="margin:4px 0 0;opacity:0.9">{$invoice->invoice_no}</p>
                </div>
                <div style="padding:24px">
                    <p>您好，您的订单发票已生成：</p>
                    <table style="width:100%;border-collapse:collapse;margin:16px 0">
                        <thead><tr style="background:#fafafa">
                            <th style="padding:8px;border:1px solid #ddd;text-align:left">商品</th>
                            <th style="padding:8px;border:1px solid #ddd">数量</th>
                            <th style="padding:8px;border:1px solid #ddd">金额</th>
                        </tr></thead>
                        <tbody>{$rows}</tbody>
                    </table>
                    <p style="font-size:16px;font-weight:600">合计：¥{$invoice->amount}</p>
                    <div style="margin-top:24px;padding:12px;background:#f0f9ff;border-radius:4px;font-size:13px;color:#606266">
                        <p style="margin:0">发票号：{$invoice->invoice_no}</p>
                        <p style="margin:4px 0 0">开票日期：{$invoice->created_at->format('Y-m-d H:i')}</p>
                    </div>
                    <div style="margin-top:16px;text-align:center">
                        <a href="{$invoice->invoice_pdf_url}"
                           style="display:inline-block;padding:10px 24px;background:#409eff;color:#fff;text-decoration:none;border-radius:4px">
                            查看/下载发票
                        </a>
                    </div>
                </div>
            </div>
        </body>
        </html>
HTML;

        return new Content(htmlString: $html);
    }
}
