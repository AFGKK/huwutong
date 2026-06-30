<?php

namespace App\Jobs;

use App\Models\NpsSurvey;
use App\Services\NpsSurveyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 发送 NPS 满意度调查邮件
 */
class SendNpsSurveyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        protected int $userId,
        protected string $channel = 'email',
    ) {}

    public function handle(NpsSurveyService $npsService): void
    {
        try {
            $survey = $npsService->sendSurvey($this->userId, $this->channel);
            Log::info('NPS survey sent', [
                'user_id' => $this->userId,
                'survey_id' => $survey?->id,
                'channel' => $this->channel,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send NPS survey', [
                'user_id' => $this->userId,
                'channel' => $this->channel,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
