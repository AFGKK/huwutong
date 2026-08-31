<?php

namespace Tests\Unit\Services;

use App\Services\Sms\AliyunSmsClient;
use App\Services\SmsService;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SmsServiceTest extends TestCase
{
    /** @test */
    public function log_driver_writes_to_log(): void
    {
        config(['sms.driver' => 'log']);

        Log::shouldReceive('info')->once();

        $result = app(SmsService::class)->sendVerificationCode('13800138000', '654321');

        $this->assertTrue($result['success']);
        $this->assertSame('log', $result['driver']);
    }

    /** @test */
    public function aliyun_driver_uses_rpc_client(): void
    {
        config([
            'sms.driver' => 'aliyun',
            'sms.fallback_to_log' => false,
            'sms.aliyun.template_code' => 'SMS_TEST',
        ]);

        $client = $this->mock(AliyunSmsClient::class);
        $client->shouldReceive('sendTemplate')
            ->once()
            ->with('13800138000', 'SMS_TEST', ['code' => '111222'])
            ->andReturn([
                'success' => true,
                'message' => '发送成功',
                'request_id' => 'req-001',
                'code' => 'OK',
            ]);

        $result = app(SmsService::class)->sendVerificationCode('13800138000', '111222');

        $this->assertTrue($result['success']);
        $this->assertSame('aliyun', $result['driver']);
    }
}
