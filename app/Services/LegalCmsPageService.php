<?php

namespace App\Services;

use App\Models\Page;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 法务/公司 CMS 页与静态 Blade 回退
 *
 * 规则：
 * - about / privacy / terms：仅当 CMS 为 published 且正文非空时用 cms-page；否则静态 Blade
 * - contact：始终使用带表单的静态页（CMS contact 仅作资料草稿，不覆盖 /contact）
 */
class LegalCmsPageService
{
    /** @var array<string, string> slug => blade view */
    public const STATIC_FALLBACKS = [
        'about' => 'public.about',
        'privacy' => 'public.privacy',
        'terms' => 'public.terms',
    ];

    /** 永不覆盖对应公开路由的 slug */
    public const FORM_RESERVED = ['contact'];

    public function resolve(string $slug): View
    {
        if (in_array($slug, self::FORM_RESERVED, true)) {
            return view('public.contact');
        }

        $page = $this->publishedWithContent($slug);
        if ($page) {
            return view('public.cms-page', [
                'page' => $page,
                'canonicalPath' => '/'.$slug,
                'usesCms' => true,
            ]);
        }

        if (isset(self::STATIC_FALLBACKS[$slug])) {
            return view(self::STATIC_FALLBACKS[$slug]);
        }

        throw new NotFoundHttpException(__("app.legal_cms_page.page_not_found"));
    }

    public function publishedWithContent(string $slug): ?Page
    {
        $page = Page::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->first();

        if (! $page) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $page->content)) ?? '');
        if ($text === '' || mb_strlen($text) < 20) {
            // 空/过短正文不覆盖精美静态页
            return null;
        }

        return $page;
    }

    public function frontendUrl(string $slug): string
    {
        if (isset(self::STATIC_FALLBACKS[$slug]) || in_array($slug, self::FORM_RESERVED, true)) {
            return url('/'.$slug);
        }

        return url('/page/'.$slug);
    }

    public function surfaceHint(string $slug): string
    {
        if (in_array($slug, self::FORM_RESERVED, true)) {
            return '联系页始终使用静态表单页；本 CMS 条目仅作文案草稿，发布不会覆盖 /contact';
        }
        if (isset(self::STATIC_FALLBACKS[$slug])) {
            return '草稿或空正文时前台显示静态精美页；发布且正文充实后自动切换为 CMS';
        }

        return '仅已发布页面可通过 /page/{slug} 访问';
    }

    /**
     * @return array{slug: string, mode: string, url: string, hint: string}
     */
    public function linkageMeta(Page $page): array
    {
        $slug = $page->slug;
        $published = $page->status === 'published';
        $hasContent = $this->publishedWithContent($slug) !== null;

        if (in_array($slug, self::FORM_RESERVED, true)) {
            $mode = 'static_form';
        } elseif (isset(self::STATIC_FALLBACKS[$slug])) {
            $mode = ($published && $hasContent) ? 'cms' : 'static_fallback';
        } else {
            $mode = $published ? 'cms' : 'draft_only';
        }

        return [
            'slug' => $slug,
            'mode' => $mode,
            'url' => $this->frontendUrl($slug),
            'hint' => $this->surfaceHint($slug),
        ];
    }
}
