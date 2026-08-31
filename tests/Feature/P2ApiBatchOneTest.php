<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * D-07 / T-11: P2 前端 API 批次一冒烟
 */
class P2ApiBatchOneTest extends TestCase
{
    /** @return array<string, list<string>> */
    private function batchOneModules(): array
    {
        return [
            'usageDashboard.js' => [
                '/usage/overview',
                '/usage/api-calls',
                '/usage/endpoint-stats',
                '/usage/features',
            ],
            'domainOverview.js' => [
                '/domain-overview/',
                '/domain-overview/domains',
            ],
            'adminAppeals.js' => [
                '/admin/appeals',
                '/admin/appeals/stats',
            ],
            'sensitiveWords.js' => [
                '/im/sensitive-words',
            ],
            'customEmoji.js' => [
                '/admin/emoji',
                '/admin/emoji/stats',
            ],
            'autoReply.js' => [
                '/user-chat/auto-reply',
                '/user-chat/auto-reply/status',
            ],
            'productSearch.js' => [
                '/admin/product-search/stats',
                '/admin/product-search/hot-terms',
            ],
            'meteredBillingDeep.js' => [
                '/admin/metered-billing/stats',
                '/admin/metered-billing/tiered-pricings',
            ],
            'cloudMarketplace.js' => [
                '/admin/marketplace/status',
                '/admin/marketplace/products',
            ],
            'workflow.js' => [
                '/admin/workflows/dashboard',
                '/admin/workflows/definitions',
            ],
            'tokenMeter.js' => [
                '/admin/token-meter/dashboard',
            ],
            'appeal.js' => [
                '/appeal/submit',
            ],
        ];
    }

    public function test_batch_one_api_files_exist(): void
    {
        foreach (array_keys($this->batchOneModules()) as $file) {
            $this->assertFileExists(base_path("resources/js/api/{$file}"), "Missing api file: {$file}");
        }
    }

    public function test_batch_one_views_import_api_modules(): void
    {
        $pairs = [
            'resources/js/views/usage-dashboard/Index.vue' => 'usageDashboard',
            'resources/js/views/domain-overview/Index.vue' => 'domainOverview',
            'resources/js/views/admin-appeals/Index.vue' => 'adminAppeals',
            'resources/js/views/sensitive-words/Index.vue' => 'sensitiveWords',
            'resources/js/views/custom-emoji/Index.vue' => 'customEmoji',
            'resources/js/views/auto-reply/Index.vue' => 'autoReply',
            'resources/js/views/product-search/Index.vue' => 'productSearch',
            'resources/js/views/monitor/Index.vue' => 'endpointUsage',
        ];

        foreach ($pairs as $view => $apiModule) {
            $content = file_get_contents(base_path($view));
            $this->assertStringContainsString("@/api/{$apiModule}", $content, "{$view} should import {$apiModule}");
        }
    }

    public function test_batch_one_api_files_reference_backend_routes(): void
    {
        foreach ($this->batchOneModules() as $file => $paths) {
            $js = file_get_contents(base_path("resources/js/api/{$file}"));
            foreach ($paths as $path) {
                $this->assertStringContainsString(trim($path, '/'), $js, "{$file} should reference {$path}");
            }
        }
    }

    public function test_workflow_and_marketplace_api_exports(): void
    {
        $workflow = file_get_contents(base_path('resources/js/api/workflow.js'));
        $marketplace = file_get_contents(base_path('resources/js/api/cloudMarketplace.js'));

        $this->assertStringContainsString('admin/workflows/dashboard', $workflow);
        $this->assertStringContainsString('admin/marketplace/products', $marketplace);
    }
}
