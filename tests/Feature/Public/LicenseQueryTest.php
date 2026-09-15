<?php

namespace Tests\Feature\Public;

use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class LicenseQueryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function license_query_page_is_accessible(): void
    {
        $response = $this->get('/license/query');

        $response->assertOk();
        $response->assertSee('授权查询');
        $response->assertSee('License Key');
        $response->assertSee('查询');
    }

    /** @test */
    public function license_query_page_has_example_keys(): void
    {
        $response = $this->get('/license/query');

        $response->assertOk();
        $response->assertSee('试试示例 Key');
        $response->assertSee('HWT-DEMO');
        $response->assertSee('HWT-ENTERPRISE');
    }

    /** @test */
    public function license_query_page_has_share_functionality(): void
    {
        $response = $this->get('/license/query');

        $response->assertOk();
        $response->assertSee('分享');
        $response->assertSee('buildShareUrl', false);
        $response->assertSee('syncQueryUrl', false);
        $response->assertSee("params.get('key')", false);
        // 分享/复制提示需底部水平居中，避免贴右上角
        $response->assertSee('id="shareToast"', false);
        $response->assertSee('left-1/2 -translate-x-1/2', false);
        $response->assertSee('text-center', false);
        $response->assertDontSee('id="shareToast" class="hidden fixed top-4 right-4', false);
    }

    /** @test */
    public function license_query_page_has_activation_guide_sections(): void
    {
        $response = $this->get('/license/query');

        $response->assertOk();
        $response->assertSee('guideActive');
        $response->assertSee('guidePending');
        $response->assertSee('guideExpired');
        $response->assertSee('guideSuspended');
        $response->assertSee('guideRevoked');
        $response->assertSee('下一步操作');
    }

    /** @test */
    public function license_query_page_has_error_and_retry(): void
    {
        $response = $this->get('/license/query');

        $response->assertOk();
        $response->assertSee('重试');
        $response->assertSee('查询失败');
    }

    /** @test */
    public function license_query_page_has_not_found_state(): void
    {
        $response = $this->get('/license/query');

        $response->assertOk();
        $response->assertSee('未找到该 License');
        $response->assertSee('修改输入');
        $response->assertSee('联系我们');
        $response->assertSee('帮助中心');
    }
}
