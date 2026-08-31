<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\SiteSetting;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        // 检查是否启用站点地图
        $enabled = SiteSetting::where('key', 'seo_enable_sitemap')->value('value');
        if ($enabled === '0' || $enabled === false) {
            return response('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>', 200, [
                'Content-Type' => 'application/xml',
            ]);
        }

        $defaultPriority = SiteSetting::where('key', 'seo_sitemap_priority')->value('value') ?: '0.5';
        $defaultFreq = SiteSetting::where('key', 'seo_sitemap_changefreq')->value('value') ?: 'weekly';

        $pages = [];

        // 静态页面
        $staticPages = [
            ['loc' => '/', 'priority' => '1.0', 'changefreq' => $defaultFreq],
            ['loc' => '/products', 'priority' => '0.9', 'changefreq' => 'daily'],
            ['loc' => '/pricing', 'priority' => '0.8', 'changefreq' => 'weekly'],
            ['loc' => '/compare', 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => '/about', 'priority' => '0.5', 'changefreq' => 'monthly'],
            ['loc' => '/contact', 'priority' => '0.5', 'changefreq' => 'monthly'],
            ['loc' => '/privacy', 'priority' => '0.4', 'changefreq' => 'monthly'],
            ['loc' => '/terms', 'priority' => '0.4', 'changefreq' => 'monthly'],
            ['loc' => '/sdk', 'priority' => '0.7', 'changefreq' => 'weekly'],
            ['loc' => '/docs/sdk', 'priority' => '0.7', 'changefreq' => 'weekly'],
            ['loc' => '/docs/quickstart', 'priority' => '0.7', 'changefreq' => 'weekly'],
            ['loc' => '/security-policy', 'priority' => '0.4', 'changefreq' => 'monthly'],
            ['loc' => '/hall-of-fame', 'priority' => '0.5', 'changefreq' => 'monthly'],
        ];

        foreach ($staticPages as $page) {
            $pages[] = $page;
        }

        foreach (array_keys(config('sdk-docs.sdks', [])) as $sdkLang) {
            $pages[] = [
                'loc' => '/docs/sdk/'.$sdkLang,
                'priority' => '0.7',
                'changefreq' => 'weekly',
            ];
        }

        // 产品详情页
        // 互物号文章
        try {
            $articles = \App\Models\OaArticle::where('status', 'published')
                ->orderByDesc('published_at')
                ->get(['id', 'title', 'published_at', 'updated_at']);

            foreach ($articles as $article) {
                $pages[] = [
                    'loc' => '/oa-article/' . $article->id,
                    'priority' => '0.6',
                    'changefreq' => 'weekly',
                    'lastmod' => ($article->updated_at ?? $article->published_at)?->toDateString(),
                ];
            }
        } catch (\Exception $e) {
            // 静默失败
        }

        try {
            $products = Product::where('is_active', true)->get(['slug', 'updated_at']);
            foreach ($products as $product) {
                $pages[] = [
                    'loc' => '/products/' . $product->slug,
                    'priority' => '0.8',
                    'changefreq' => 'daily',
                    'lastmod' => $product->updated_at?->toDateString(),
                ];
            }
        } catch (\Exception $e) {
            // 静默失败
        }

        // 生成 XML
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($pages as $page) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . url($page['loc']) . '</loc>' . "\n";
            if (!empty($page['lastmod'])) {
                $xml .= '    <lastmod>' . $page['lastmod'] . '</lastmod>' . "\n";
            }
            $xml .= '    <changefreq>' . $page['changefreq'] . '</changefreq>' . "\n";
            $xml .= '    <priority>' . $page['priority'] . '</priority>' . "\n";
            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>' . "\n";

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }
}
