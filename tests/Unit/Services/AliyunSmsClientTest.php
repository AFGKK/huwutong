<?php

namespace Tests\Unit\Services;

use App\Services\Sms\AliyunSmsClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AliyunSmsClientTest extends TestCase
{
    /** @test */
    public function it_sends_sms_via_aliyun_rpc_api(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'dysmsapi.aliyuncs.com')) {
                return Http::response([
                    'Code' => 'OK',
                    'Message' => 'OK',
                    'RequestId' => 'req-test-001',
                ]);
            }
        });

        $client = new AliyunSmsClient([
            'access_key_id' => 'test-key',
            'access_key_secret' => 'test-secret',
            'sign_name' => '互物通',
            'region_id' => 'cn-hangzhou',
        ]);

        $result = $client->sendTemplate('13800138000', 'SMS_123456', ['code' => '123456']);

        $this->assertTrue($result['success']);
        $this->assertSame('OK', $result['code']);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'dysmsapi.aliyuncs.com')) {
                return false;
            }
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

            return ($query['Action'] ?? '') === 'SendSms'
                && ($query['PhoneNumbers'] ?? '') === '13800138000'
                && isset($query['Signature']);
        });
    }

    /** @test */
    public function it_returns_error_when_config_missing(): void
    {
        $client = new AliyunSmsClient([]);

        $result = $client->sendTemplate('13800138000', '', ['code' => '1']);

        $this->assertFalse($result['success']);
        $this->assertSame('CONFIG_ERROR', $result['code']);
    }
}
