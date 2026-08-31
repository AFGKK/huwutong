<?php

namespace App\Mail;

use App\Models\CustomReport;
use App\Models\ReportSnapshot;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReportDeliveryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public CustomReport $report,
        public ReportSnapshot $snapshot,
        public string $format,
        public ?string $filePath = null,
        public ?string $customSubject = null,
        public ?string $customMessage = null,
        public ?string $recipientName = null,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->customSubject
            ?? __('app.mail.report_subject', ['name' => $this->report->name, 'date' => now()->format('Y-m-d')]);
        return new Envelope(
            subject: $subject . ' - HWT License',
        );
    }

    public function content(): Content
    {
        $summary = $this->snapshot->summary ?? [];
        $rows = $this->snapshot->row_count ?? 0;
        $generatedAt = $this->snapshot->generated_at
            ? $this->snapshot->generated_at->format('Y-m-d H:i')
            : now()->format('Y-m-d H:i');
        $greeting = $this->recipientName ? __('app.mail.greeting_name', ['name' => $this->recipientName]) : __('app.mail.greeting');
        $message = $this->customMessage ?? __('app.mail.report_body', ['name' => $this->report->name]);

        // Build summary HTML
        $summaryHtml = '';
        if (!empty($summary)) {
            $summaryHtml .= '<table style="width:100%; border-collapse: collapse; margin: 16px 0;">';
            foreach ($summary as $key => $val) {
                $label = is_array($val) ? ($val['label'] ?? $key) : $key;
                $total = is_array($val) ? ($val['total'] ?? '') : $val;
                $avg = is_array($val) && isset($val['avg']) ? __('app.mail.report_avg', ['avg' => $val['avg']]) : '';
                $summaryHtml .= "<tr><td style='padding: 6px 12px; border: 1px solid #eee; font-weight: 600;'>{$label}</td><td style='padding: 6px 12px; border: 1px solid #eee;'>{$total}{$avg}</td></tr>";
            }
            $summaryHtml .= '</table>';
        }

                $reportTitlePrefix = __('app.mail.report_title_prefix', ['name' => $this->report->name]);
        $dataRowsLabel = __('app.mail.data_rows');
        $exportFormatLabel = __('app.mail.export_format');
        $generatedTimeLabel = __('app.mail.generated_time');
        $autoSendNoReply2 = __('app.mail.auto_send_no_reply');

        return new Content(htmlString: <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; padding: 40px; background: #f5f5f5;">
    <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 32px;">
        <h2 style="margin-top: 0; color: #0f172a;">{$reportTitlePrefix}</h2>
        <p style="color: #666; line-height: 1.6;">{$greeting}</p>
        <p style="color: #333; line-height: 1.6;">{$message}</p>

        <div style="background: #f0f9ff; border-radius: 6px; padding: 16px; margin: 16px 0;">
            <table style="width:100%;">
                <tr>
                    <td style="text-align:center; padding: 8px;"><div style="font-size: 24px; font-weight: 700; color: #0f172a;">{$rows}</div><div style="font-size: 12px; color: #999;">{$dataRowsLabel}</div></td>
                    <td style="text-align:center; padding: 8px;"><div style="font-size: 24px; font-weight: 700; color: #67c23a;">{$this->format}</div><div style="font-size: 12px; color: #999;">{$exportFormatLabel}</div></td>
                    <td style="text-align:center; padding: 8px;"><div style="font-size: 14px; font-weight: 600; color: #666;">{$generatedAt}</div><div style="font-size: 12px; color: #999;">{$generatedTimeLabel}</div></td>
                </tr>
            </table>
        </div>

        {$summaryHtml}

        <p style="color: #999; font-size: 12px; margin-top: 20px;">
            __('app.mail.data_source_label'): {$this->report->data_source} | __('app.mail.category_label'): {$this->report->category}
        </p>
        <hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">
        <p style="color: #999; font-size: 12px;">{$autoSendNoReply2}</p>
    </div>
</body>
</html>
HTML
        );
    }

    public function attachments(): array
    {
        if (!$this->filePath || !file_exists(storage_path("app/{$this->filePath}"))) {
            return [];
        }

        $filename = \Illuminate\Support\Str::slug($this->report->name)
            . '-' . now()->format('YmdHis')
            . '.' . $this->format;

        return [
            Attachment::fromPath(storage_path("app/{$this->filePath}"))
                ->as($filename)
                ->withMime($this->getMimeType()),
        ];
    }

    protected function getMimeType(): string
    {
        return match ($this->format) {
            'csv' => 'text/csv',
            'json' => 'application/json',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }
}
