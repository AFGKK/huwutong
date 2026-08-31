<?php

namespace Tests\Unit\Services;

use App\Mail\VerifyCodeMail;
use App\Services\SmsService;
use App\Services\VerifyCodeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class VerifyCodeServiceTest extends TestCase
{
    /** @test */
    public function it_sends_email_verification_code(): void
    {
        Mail::fake();

        $result = app(VerifyCodeService::class)->sendEmail('user@example.com', 'login');

        $this->assertTrue($result['success']);
        Mail::assertSent(VerifyCodeMail::class);
        $this->assertNotNull(Cache::get('verify_code:email:user@example.com:login'));
    }

    /** @test */
    public function it_sends_sms_and_stores_code_in_cache(): void
    {
        $this->mock(SmsService::class, function ($mock) {
            $mock->shouldReceive('sendVerificationCode')
                ->once()
                ->andReturn(['success' => true, 'message' => 'ok', 'driver' => 'log']);
        });

        $result = app(VerifyCodeService::class)->sendSms('13800138000', 'login');

        $this->assertTrue($result['success']);
        $this->assertNotNull(Cache::get('verify_code:sms:13800138000'));
    }

    /** @test */
    public function it_verifies_sms_code_from_cache(): void
    {
        Cache::put('verify_code:sms:13800138000', '123456', 300);

        $result = app(VerifyCodeService::class)->verifySms('13800138000', '123456');

        $this->assertTrue($result['success']);
        $this->assertNull(Cache::get('verify_code:sms:13800138000'));
    }

    /** @test */
    public function it_verifies_email_code_from_cache(): void
    {
        Cache::put('verify_code:email:user@example.com:register', '888888', 600);

        $result = app(VerifyCodeService::class)->verifyEmail('user@example.com', '888888', 'register');

        $this->assertTrue($result['success']);
        $this->assertNull(Cache::get('verify_code:email:user@example.com:register'));
    }
}
