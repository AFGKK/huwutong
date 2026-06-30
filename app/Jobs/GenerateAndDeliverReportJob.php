<?php

namespace App\Jobs;

use App\Mail\ReportDeliveryMail;
use App\Models\CustomReport;
use App\Models\ReportDeliveryLog;
use App\Models\ReportSchedule;
use App\Models\ReportSnapshot;
use App\Services\ReportBuilderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class GenerateAndDeliverReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $maxExceptions = 3;

    public function __construct(
        public ReportSchedule $schedule,
    ) {}

    public function handle(ReportBuilderService $reportBuilder): void
    {
        $deliveryLog = null;

        try {
            $report = $this->schedule->report;
            if (!$report) {
                $this->fail("报表不存在 (ID: {$this->schedule->report_id})");
                return;
            }

            // 1. 创建投递日志
            $deliveryLog = ReportDeliveryLog::create([
                'schedule_id' => $this->schedule->id,
                'report_id' => $report->id,
                'status' => 'processing',
                'export_format' => $this->schedule->export_format,
                'recipients' => $this->schedule->recipients,
                'attempts' => 1,
                'started_at' => now(),
            ]);

            // 2. 生成报表快照
            $snapshot = $reportBuilder->generateSnapshot($report);

            // 3. 导出文件
            $exportResult = $reportBuilder->exportReport($report, $this->schedule->export_format);

            $deliveryLog->update([
                'snapshot_id' => $snapshot->id,
                'file_path' => $exportResult['path'] ?? null,
                'file_size' => $exportResult['size'] ?? null,
            ]);

            // 4. 发送邮件给所有接收人
            $recipients = $this->schedule->recipients ?? [];
            $deliveryResults = [];

            if (!empty($recipients)) {
                foreach ($recipients as $recipient) {
                    $email = is_string($recipient) ? $recipient : ($recipient['email'] ?? null);
                    $name = is_array($recipient) ? ($recipient['name'] ?? null) : null;

                    if (!$email) continue;

                    try {
                        Mail::to($email, $name)->send(new ReportDeliveryMail(
                            report: $report,
                            snapshot: $snapshot,
                            format: $this->schedule->export_format,
                            filePath: $exportResult['path'] ?? null,
                            customSubject: $this->schedule->subject,
                            customMessage: $this->schedule->message,
                            recipientName: $name,
                        ));

                        $deliveryResults[] = [
                            'email' => $email,
                            'name' => $name,
                            'status' => 'sent',
                            'sent_at' => now()->toIso8601String(),
                        ];
                    } catch (\Exception $e) {
                        $deliveryResults[] = [
                            'email' => $email,
                            'name' => $name,
                            'status' => 'failed',
                            'error' => $e->getMessage(),
                        ];
                    }
                }
            }

            // 5. 更新投递日志
            $deliveryLog->update([
                'status' => 'completed',
                'delivery_results' => $deliveryResults,
                'completed_at' => now(),
            ]);

            // 6. 更新调度状态
            $this->schedule->update([
                'last_run_at' => now(),
                'last_success_at' => now(),
                'next_run_at' => $this->calculateNextRun(),
                'run_count' => $this->schedule->run_count + 1,
                'success_count' => $this->schedule->success_count + 1,
                'last_error' => null,
            ]);

            // 7. 更新报表上次生成时间
            $report->update(['last_generated_at' => now()]);

        } catch (\Exception $e) {
            Log::error("报表调度投递失败 [{$this->schedule->id}]: {$e->getMessage()}", [
                'schedule_id' => $this->schedule->id,
                'report_id' => $this->schedule->report_id,
                'trace' => $e->getTraceAsString(),
            ]);

            if ($deliveryLog) {
                $deliveryLog->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'completed_at' => now(),
                ]);
            }

            $this->schedule->update([
                'last_run_at' => now(),
                'last_failure_at' => now(),
                'last_error' => substr($e->getMessage(), 0, 500),
                'run_count' => $this->schedule->run_count + 1,
                'failure_count' => $this->schedule->failure_count + 1,
                'next_run_at' => $this->calculateNextRun(),
            ]);

            // 重试
            if ($this->attempts() < $this->schedule->max_retries) {
                $this->release(60 * pow(2, $this->attempts())); // exponential backoff
            }

            throw $e;
        }
    }

    /**
     * 基于 cron 表达式计算下次运行时间
     */
    protected function calculateNextRun(): ?\DateTime
    {
        $cron = $this->schedule->cron_expression;
        if (empty($cron) || $cron === '* * * * *') {
            // Default: every hour
            return now()->addHour();
        }

        $parts = explode(' ', $cron);
        if (count($parts) !== 5) {
            return now()->addHour(); // fallback
        }

        [$minute, $hour, $dayOfMonth, $month, $dayOfWeek] = $parts;

        // Simple next-run calculation for common patterns
        $next = now();

        if ($minute !== '*') {
            $min = (int) $minute;
            if ($next->minute >= $min) {
                $next->addHour();
            }
            $next->setMinute($min)->setSecond(0);
        } elseif ($hour !== '*') {
            $hr = (int) $hour;
            if ($next->hour >= $hr) {
                $next->addDay();
            }
            $next->setHour($hr)->setMinute(0)->setSecond(0);
        } else {
            $next->addHour();
        }

        return $next;
    }

    public function failed(\Throwable $e): void
    {
        Log::error("报表调度投递最终失败 [{$this->schedule->id}]: {$e->getMessage()}", [
            'schedule_id' => $this->schedule->id,
        ]);
    }
}
