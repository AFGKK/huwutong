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
            subject: __('app.mail.invoice_subject', ['app' => config('app.name'), 'no' => $this->invoice->invoice_no]),
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

        $invoiceDateLabel = __('app.mail.invoice_date', ['date' => $invoice->created_at->format('Y-m-d H:i')]);
                $invoiceTitleLabel = __('app.mail.invoice_title');
        $invoiceGreetingLabel = __('app.mail.invoice_greeting');
        $productLabel = __('app.mail.product');
        $quantityLabel = __('app.mail.quantity');
        $amountLabelInv = __('app.mail.amount_label');
        $totalLabel = __('app.mail.invoice_total');
        $invoiceNoLabel = __('app.mail.invoice_no_label');
        $viewDownloadLabel = __('app.mail.view_download_invoice');
$html = <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8"></head>
        <body style="font-family:'Helvetica Neue',Arial,sans-serif;padding:20px;background:#f5f5f5">
            <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden">
                <div style="background:#0f172a;color:#fff;padding:24px;text-align:center">
                    <h2 style="margin:0">{$invoiceTitleLabel}</h2>
                    <p style="margin:4px 0 0;opacity:0.9">{$invoice->invoice_no}</p>
                </div>
                <div style="padding:24px">
                    <p>{$invoiceGreetingLabel}</p>
                    <table style="width:100%;border-collapse:collapse;margin:16px 0">
                        <thead><tr style="background:#fafafa">
                            <th style="padding:8px;border:1px solid #ddd;text-align:left">{$productLabel}</th>
                            <th style="padding:8px;border:1px solid #ddd">{$quantityLabel}</th>
                            <th style="padding:8px;border:1px solid #ddd">{$amountLabelInv}</th>
                        </tr></thead>
                        <tbody>{$rows}</tbody>
                    </table>
                    <p style="font-size:16px;font-weight:600">{$totalLabel}¥{$invoice->amount}</p>
                    <div style="margin-top:24px;padding:12px;background:#f0f9ff;border-radius:4px;font-size:13px;color:#606266">
                        <p style="margin:0">{$invoiceNoLabel}{$invoice->invoice_no}</p>
                        <p style="margin:4px 0 0">{$invoiceDateLabel}</p>
                    </div>
                    <div style="margin-top:16px;text-align:center">
                        <a href="{$invoice->invoice_pdf_url}"
                           style="display:inline-block;padding:10px 24px;background:#0f172a;color:#fff;text-decoration:none;border-radius:4px">
                            {$viewDownloadLabel}
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
