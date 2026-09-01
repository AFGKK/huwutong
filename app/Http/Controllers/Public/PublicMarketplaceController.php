<?php

namespace App\Http\Controllers\Public;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\MarketplaceApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 公开应用市场（管理端 MarketplaceApp published → 前台浏览）
 */
class PublicMarketplaceController extends Controller
{
    public function index(Request $request): View
    {
        $category = trim((string) $request->query('category', ''));
        $search = trim((string) $request->query('q', ''));

        $apps = $this->publishedQuery($category, $search)
            ->paginate(12)
            ->withQueryString();

        $categories = MarketplaceApp::query()
            ->where('status', 'published')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('public.marketplace', [
            'apps' => $apps,
            'categories' => $categories,
            'activeCategory' => $category,
            'search' => $search,
        ]);
    }

    public function show(string $slug): View
    {
        $app = MarketplaceApp::query()
            ->with('developer:id,display_name,company_name,status')
            ->where('status', 'published')
            ->where(function ($q) use ($slug) {
                $q->where('slug', $slug);
                if (ctype_digit($slug)) {
                    $q->orWhere('id', (int) $slug);
                }
            })
            ->firstOrFail();

        return view('public.marketplace-detail', [
            'app' => $app,
        ]);
    }

    public function apiIndex(Request $request): JsonResponse
    {
        $apps = $this->publishedQuery(
            trim((string) $request->query('category', '')),
            trim((string) $request->query('q', $request->query('search', ''))),
        )->paginate((int) $request->input('per_page', 20));

        return ApiResponse::success($apps);
    }

    public function apiShow(string $slug): JsonResponse
    {
        $app = MarketplaceApp::query()
            ->with('developer:id,display_name,company_name,status')
            ->where('status', 'published')
            ->where(function ($q) use ($slug) {
                $q->where('slug', $slug);
                if (ctype_digit($slug)) {
                    $q->orWhere('id', (int) $slug);
                }
            })
            ->firstOrFail();

        return ApiResponse::success($app);
    }

    private function publishedQuery(string $category = '', string $search = '')
    {
        $query = MarketplaceApp::query()
            ->with('developer:id,display_name,company_name')
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->orderByDesc('install_count');

        if ($category !== '') {
            $query->where('category', $category);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        return $query;
    }
}
