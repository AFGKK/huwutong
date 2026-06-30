<?php

namespace App\Services;

use App\Models\PromptTemplate;
use Illuminate\Support\Facades\Log;

class PromptTemplateService
{
    /**
     * 获取模板列表
     */
    public function list(array $filters = [], int $perPage = 20)
    {
        $q = PromptTemplate::query();
        if (!empty($filters['category'])) $q->byCategory($filters['category']);
        if (!empty($filters['status'])) $q->where('status', $filters['status']);
        if (!empty($filters['search'])) $q->where(function($q) use ($filters) {
            $q->where('name', 'like', "%{$filters['search']}%")
              ->orWhere('description', 'like', "%{$filters['search']}%");
        });
        return $q->with('creator:id,name')
            ->orderBy('category')->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * 获取活跃模板（按分类分组）
     */
    public function getActiveGrouped(): array
    {
        return PromptTemplate::active()->current()
            ->get()
            ->groupBy('category')
            ->toArray();
    }

    /**
     * 获取指定场景的生效模板
     */
    public function getActiveTemplate(string $category, ?string $name = null): ?PromptTemplate
    {
        $q = PromptTemplate::active()->current()->byCategory($category);
        if ($name) $q->where('name', $name);
        return $q->first();
    }

    /**
     * 渲染模板：替换变量
     */
    public function render(PromptTemplate $template, array $vars = []): string
    {
        $content = $template->content;
        foreach ($vars as $key => $value) {
            $content = str_replace('{' . $key . '}', (string) $value, $content);
        }
        return $content;
    }

    /**
     * 按名称+分类渲染（快捷方法）
     */
    public function renderByCategory(string $category, array $vars = [], ?string $name = null): string
    {
        $template = $this->getActiveTemplate($category, $name);
        if (!$template) {
            Log::warning('PromptTemplate not found', ['category' => $category, 'name' => $name]);
            return '';
        }
        // A/B 测试分流
        $variant = $this->resolveAbVariant($template);
        $content = $variant ?: $template->content;
        return $this->renderContent($content, $vars);
    }

    /**
     * A/B 测试分流
     */
    protected function resolveAbVariant(PromptTemplate $template): ?string
    {
        $config = $template->ab_test_config;
        if (!$config || empty($config['enabled']) || empty($config['variants'])) return null;
        $variants = $config['variants'];
        $traffic = $config['traffic_split'] ?? 50;
        $bucket = mt_rand(1, 100);
        return $bucket <= $traffic && isset($variants[0]) ? $variants[0]['content'] ?? null : null;
    }

    protected function renderContent(string $content, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $content = str_replace('{' . $key . '}', (string) $value, $content);
        }
        return $content;
    }

    /**
     * 创建新版本
     */
    public function createVersion(PromptTemplate $template, string $newContent, ?string $note = null): PromptTemplate
    {
        $parts = explode('.', $template->version);
        $newVersion = $parts[0] . '.' . ((int)($parts[1] ?? 0) + 1);

        $new = PromptTemplate::create([
            'name' => $template->name,
            'category' => $template->category,
            'content' => $newContent,
            'description' => $note ?: $template->description,
            'variables' => $template->variables,
            'version' => $newVersion,
            'status' => 'active',
            'is_current' => true,
            'engine' => $template->engine,
            'temperature' => $template->temperature,
            'max_tokens' => $template->max_tokens,
            'created_by' => auth()->id(),
        ]);

        // 旧模板取消当前标志
        $template->update(['is_current' => false]);

        Log::info('PromptTemplate: new version created', [
            'name' => $template->name, 'version' => $newVersion,
        ]);

        return $new;
    }

    /**
     * 仪表盘统计
     */
    public function getDashboard(): array
    {
        $total = PromptTemplate::count();
        $active = PromptTemplate::active()->count();
        $drafts = PromptTemplate::where('status', 'draft')->count();
        $byCategory = PromptTemplate::selectRaw('category, COUNT(*) as count')
            ->groupBy('category')->pluck('count', 'category')->toArray();

        return compact('total', 'active', 'drafts', 'byCategory');
    }
}
