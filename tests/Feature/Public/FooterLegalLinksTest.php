<?php

namespace Tests\Feature\Public;

use App\Models\FooterNavItem;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class FooterLegalLinksTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function footer_renders_legal_links_under_company_column_by_default(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString(__('app.footer.company'), $html);
        $this->assertStringContainsString(__('app.footer.terms_of_service'), $html);
        $this->assertStringContainsString(__('app.footer.privacy_policy'), $html);
        $this->assertStringContainsString(__('app.footer.security_policy'), $html);
        $this->assertStringContainsString(__('app.footer.cookie_policy'), $html);

        $companyPos = strpos($html, __('app.footer.company'));
        $termsPos = strpos($html, 'href="'.url('/terms').'"');
        $this->assertNotFalse($companyPos);
        $this->assertNotFalse($termsPos);
        $this->assertLessThan($termsPos, $companyPos);

        $this->assertStringContainsString(
            'class="footer-nav-link">'.__('app.footer.terms_of_service').'</a>',
            $html
        );
        $this->assertStringContainsString(
            'class="footer-nav-link">'.__('app.footer.privacy_policy').'</a>',
            $html
        );
        $this->assertStringContainsString(
            'class="footer-nav-link">'.__('app.footer.security_policy').'</a>',
            $html
        );
        $this->assertStringContainsString(
            'class="footer-nav-link">'.__('app.footer.cookie_policy').'</a>',
            $html
        );
        $this->assertStringNotContainsString(
            'hover:text-gray-300 transition">'.__('app.footer.terms_of_service').'</a>',
            $html
        );
    }

    /** @test */
    public function footer_moves_legacy_bottom_group_legal_links_into_company_column(): void
    {
        FooterNavItem::create([
            'label' => '关于我们',
            'type' => 'page',
            'url' => '/about',
            'target' => '_self',
            'group' => 'footer',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        FooterNavItem::create([
            'label' => '服务条款',
            'type' => 'page',
            'url' => '/terms',
            'target' => '_self',
            'group' => 'bottom',
            'sort_order' => 20,
            'is_active' => true,
        ]);
        FooterNavItem::create([
            'label' => '隐私政策',
            'type' => 'page',
            'url' => '/privacy',
            'target' => '_self',
            'group' => 'bottom',
            'sort_order' => 30,
            'is_active' => true,
        ]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('footer-nav-link', $html);
        $this->assertStringContainsString(__('app.footer.terms_of_service'), $html);
        $this->assertSame(1, substr_count($html, 'href="'.url('/terms').'"'));
    }
}
