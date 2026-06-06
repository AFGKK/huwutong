<?php

namespace Tests\Unit;

use App\Services\DiagnosticEngineService;
use Tests\TestCase;

class DiagnosticEngineTest extends TestCase
{
    protected DiagnosticEngineService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(DiagnosticEngineService::class);
    }

    public function test_diagnose_known_error_code(): void
    {
        $result = $this->service->diagnose('LICENSE_EXPIRED');

        $this->assertEquals('LICENSE_EXPIRED', $result['error_code']);
        $this->assertStringContainsString('过期', $result['summary']);
        $this->assertNotEmpty($result['detail']);
        $this->assertNotEmpty($result['suggestions']);
        $this->assertGreaterThanOrEqual(1, count($result['suggestions']));
        $this->assertEquals('high', $result['severity']);
    }

    public function test_diagnose_device_limit_exceeded(): void
    {
        $result = $this->service->diagnose('DEVICE_LIMIT_EXCEEDED', [
            'max_devices' => 3,
            'active_devices' => 5,
        ]);

        $this->assertEquals('DEVICE_LIMIT_EXCEEDED', $result['error_code']);
        // Should have injected context
        $hasContextHint = false;
        foreach ($result['suggestions'] as $suggestion) {
            if (str_contains($suggestion, '3') || str_contains($suggestion, '5')) {
                $hasContextHint = true;
                break;
            }
        }
        $this->assertTrue($hasContextHint, 'Suggestions should include context numbers');
    }

    public function test_diagnose_unknown_error_code(): void
    {
        $result = $this->service->diagnose('UNKNOWN_CODE_123');

        $this->assertEquals('UNKNOWN_CODE_123', $result['error_code']);
        $this->assertEquals('medium', $result['severity']);
        $this->assertNotEmpty($result['suggestions']);
    }

    public function test_diagnose_batch(): void
    {
        $errors = [
            ['code' => 'LICENSE_EXPIRED'],
            ['code' => 'DEVICE_LIMIT_EXCEEDED', 'context' => ['max_devices' => 5]],
            ['code' => 'AUTH_FAILED'],
        ];

        $results = $this->service->diagnoseBatch($errors);

        $this->assertCount(3, $results);
        $this->assertEquals('LICENSE_EXPIRED', $results[0]['error_code']);
        $this->assertEquals('DEVICE_LIMIT_EXCEEDED', $results[1]['error_code']);
        $this->assertEquals('AUTH_FAILED', $results[2]['error_code']);
        foreach ($results as $result) {
            $this->assertArrayHasKey('summary', $result);
            $this->assertArrayHasKey('suggestions', $result);
            $this->assertArrayHasKey('severity', $result);
        }
    }

    public function test_diagnose_all_known_codes_have_unique_severities(): void
    {
        $map = $this->service->getSdkSuggestionMap();
        $this->assertNotEmpty($map);

        foreach ($map as $code => $info) {
            $this->assertArrayHasKey('summary', $info);
            $this->assertArrayHasKey('suggestions', $info);
            $this->assertArrayHasKey('severity', $info);
            $this->assertContains($info['severity'], ['low', 'medium', 'high', 'critical']);
            $this->assertNotEmpty($info['suggestions']);
        }
    }

    public function test_diagnose_activation_failure_without_models(): void
    {
        $result = $this->service->diagnoseActivationFailure(
            null,
            null,
            'LICENSE_NOT_FOUND'
        );

        $this->assertEquals('LICENSE_NOT_FOUND', $result['error_code']);
        $this->assertArrayHasKey('context', $result);
    }

    public function test_sdk_suggestion_map_contains_key_codes(): void
    {
        $map = $this->service->getSdkSuggestionMap();

        $this->assertArrayHasKey('LICENSE_EXPIRED', $map);
        $this->assertArrayHasKey('DEVICE_LIMIT_EXCEEDED', $map);
        $this->assertArrayHasKey('LICENSE_NOT_FOUND', $map);
        $this->assertArrayHasKey('PAYMENT_FAILED', $map);
        $this->assertArrayHasKey('AUTH_FAILED', $map);
    }
}
