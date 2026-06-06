<?php

namespace App\Services;

use App\Models\EmailLog;
use App\Models\EmailTemplate;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailTemplateService
{
    /**
     * 使用模板发送邮件
     */
    public function send(string $templateCode, string $to, array $data = [], ?array $options = []): bool
    {
        $template = EmailTemplate::where('code', $templateCode)
            ->where('status', 'published')
            ->first();

        if (! $template) {
            Log::warning('邮件模板不存在或未发布', ['template_code' => $templateCode]);
            return false;
        }

        $rendered = $template->render($data);

        $fromEmail = $options['from'] ?? config('mail.from.address');
        $fromName = $options['from_name'] ?? config('mail.from.name');

        try {
            Mail::send([], [], function (Message $message) use ($to, $rendered, $fromEmail, $fromName) {
                $message->to($to)
                    ->subject($rendered['subject'])
                    ->from($fromEmail, $fromName)
                    ->html($rendered['html']);

                if (! empty($rendered['text'])) {
                    $message->text($rendered['text']);
                }
            });

            $this->log($templateCode, $to, $rendered['subject'], 'sent');

            return true;
        } catch (\Exception $e) {
            Log::error('邮件发送失败', [
                'template_code' => $templateCode,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            $this->log($templateCode, $to, $rendered['subject'], 'failed', $e->getMessage());

            return false;
        }
    }

    /**
     * 记录邮件日志
     */
    public function log(string $templateCode, string $to, string $subject, string $status, ?string $error = null): void
    {
        EmailLog::create([
            'template_code' => $templateCode,
            'to_email' => $to,
            'subject' => $subject,
            'status' => $status,
            'error_message' => $error,
            'sent_at' => $status === 'sent' ? now() : null,
        ]);
    }
}
