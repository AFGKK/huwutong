<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * D-08 / T-11: P2 前端 API 批次二冒烟
 */
class P2ApiBatchTwoTest extends TestCase
{
    /** @return array<string, list<string>> */
    private function batchTwoModules(): array
    {
        return [
            'hsm.js' => [
                '/hsm/health',
                '/hsm/stats',
                '/hsm/keys',
            ],
            'points.js' => [
                '/points/admin/stats',
                '/points/admin/users',
                '/points/grant',
            ],
            'chaosEngineering.js' => [
                '/chaos-engineering/dashboard',
                '/chaos-engineering/scorecard',
                '/chaos-engineering/types',
            ],
            'blueGreen.js' => [
                '/admin/blue-green/dashboard',
                '/admin/blue-green/history',
            ],
            'edge-verifier.js' => [
                '/admin/edge/dashboard',
                '/admin/edge/deployment-guide',
            ],
            'openFeature.js' => [
                '/openfeature/manage/flags',
                '/openfeature/health',
                '/openfeature/evaluate',
            ],
            'featureFlag.js' => [
                '/feature-flags',
                '/feature-flags/assignments',
            ],
            'sso.js' => [
                '/sso/providers',
                '/sso/connections',
            ],
            'embeddedWidget.js' => [
                '/widget/token',
            ],
            'diagnostic.js' => [
                '/diagnostic/activation',
                '/diagnostic/sdk-suggestions',
            ],
            'billingCycle.js' => [
                '/admin/billing-cycles',
            ],
            'legal-consent.js' => [
                '/legal-consents',
            ],
            'gdprCompliance.js' => [
                '/gdpr/requests',
                '/gdpr/dpa',
            ],
            'preSale.js' => [
                '/admin/pre-sale/stats',
                '/admin/pre-sale',
            ],
            'csm.js' => [
                '/csm/dashboard',
                '/csm/customers',
            ],
        ];
    }

    public function test_batch_two_api_files_exist(): void
    {
        foreach (array_keys($this->batchTwoModules()) as $file) {
            $this->assertFileExists(base_path("resources/js/api/{$file}"), "Missing api file: {$file}");
        }
    }

    public function test_batch_two_views_import_api_modules(): void
    {
        $pairs = [
            'resources/js/views/hsm/Index.vue' => 'hsm',
            'resources/js/views/points/Index.vue' => 'points',
            'resources/js/views/chaos-engineering/Index.vue' => 'chaosEngineering',
            'resources/js/views/blue-green/Index.vue' => 'blueGreen',
            'resources/js/views/edge-verifier/Index.vue' => 'edge-verifier',
            'resources/js/views/openfeature/Index.vue' => 'openFeature',
            'resources/js/views/feature-flags/Index.vue' => 'featureFlag',
            'resources/js/views/sso/Index.vue' => 'sso',
            'resources/js/views/embedded-widget/Index.vue' => 'embeddedWidget',
            'resources/js/views/diagnostic/Index.vue' => 'diagnostic',
            'resources/js/views/billing-cycles/Index.vue' => 'billingCycle',
            'resources/js/views/legal-consent/Index.vue' => 'legal-consent',
            'resources/js/views/gdpr/Index.vue' => 'gdprCompliance',
            'resources/js/views/pre-sale/Index.vue' => 'preSale',
            'resources/js/views/csm/Index.vue' => 'csm',
            'resources/js/views/product-comparison/Index.vue' => 'productComparison',
            'resources/js/views/product-sku/Index.vue' => 'productSku',
        ];

        foreach ($pairs as $view => $apiModule) {
            $content = file_get_contents(base_path($view));
            $this->assertStringContainsString("@/api/{$apiModule}", $content, "{$view} should import {$apiModule}");
        }
    }

    public function test_batch_two_api_files_reference_backend_routes(): void
    {
        foreach ($this->batchTwoModules() as $file => $paths) {
            $js = file_get_contents(base_path("resources/js/api/{$file}"));
            foreach ($paths as $path) {
                $needle = trim($path, '/');
                $segments = explode('/', $needle);
                $matched = collect($segments)->every(
                    fn (string $segment) => str_contains($js, $segment)
                );
                $this->assertTrue($matched, "{$file} should reference {$path}");
            }
        }
    }

    public function test_chaos_api_uses_correct_route_prefix(): void
    {
        $js = file_get_contents(base_path('resources/js/api/chaosEngineering.js'));
        $this->assertStringContainsString('/chaos-engineering', $js);
        $this->assertStringNotContainsString('/admin/chaos', $js);
    }
}
