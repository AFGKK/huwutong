<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;

/**
 * D-22: 前端语言切换 API
 *
 * POST /api/locale/switch   — 切换语言
 * GET  /api/locale/current  — 获取当前语言
 * GET  /api/locale/supported — 获取支持的语言列表
 */
class LocaleController extends Controller
{
    /** 语言 → 显示名称映射 */
    protected array $locales = [
        'zh_CN' => '简体中文',
        'en' => 'English',
    ];

    public function switch(Request $request): JsonResponse
    {
        $request->validate([
            'locale' => 'required|string|in:' . implode(',', array_keys($this->locales)),
        ]);

        $locale = $request->input('locale');
        App::setLocale($locale);
        session(['locale' => $locale]);

        // 设置 Cookie（有效期 1 年）
        Cookie::queue('locale', $locale, 60 * 24 * 365);

        return response()->json([
            'locale' => $locale,
            'name' => $this->locales[$locale] ?? $locale,
            'message' => __('app.language.switch_language'),
        ]);
    }

    public function current(): JsonResponse
    {
        $locale = App::getLocale();

        return response()->json([
            'locale' => $locale,
            'name' => $this->locales[$locale] ?? $locale,
        ]);
    }

    public function supported(): JsonResponse
    {
        $locales = [];
        foreach ($this->locales as $code => $name) {
            $locales[] = [
                'code' => $code,
                'name' => $name,
                'native' => $name,
                'current' => $code === App::getLocale(),
            ];
        }

        return response()->json([
            'locales' => $locales,
            'current' => App::getLocale(),
        ]);
    }
}
