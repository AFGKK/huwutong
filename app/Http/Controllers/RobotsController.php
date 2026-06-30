<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $custom = SiteSetting::where('key', 'seo_robots_txt')->value('value');

        // 如果自定义内容中没有 Sitemap 行，自动追加
        if (!empty($custom)) {
            $content = $custom;
            if (!str_contains($content, 'Sitemap:')) {
                $content .= "\nSitemap: " . url('/sitemap.xml');
            }
        } else {
            $content = "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /build\nDisallow: /api\nSitemap: " . url('/sitemap.xml');
        }

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }
}
