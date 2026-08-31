<?php

namespace App\Services;

use App\Models\Language;
use App\Models\Translation;
use App\Models\TranslationHistory;
use App\Models\TranslationNamespace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 多语言自动翻译引擎
 *
 * M3-85: 集成 LLM 服务实现真正的自动翻译：
 * - 支持批量翻译缺失条目
 * - 翻译记忆（避免重复翻译相同文本）
 * - LLM Prompt 优化（上下文感知翻译）
 * - 翻译质量评估
 * - 支持增量翻译（仅翻译新增/变更内容）
 */
class TranslationEngineService
{
    protected LlmService $llm;
    protected array $translationMemory = [];

    public function __construct(LlmService $llm)
    {
        $this->llm = $llm;
    }

    /**
     * 翻译单个条目（通过 LLM）
     */
    public function translateSingle(int $translationId, ?int $userId = null): Translation
    {
        $translation = Translation::with('namespace')->findOrFail($translationId);

        if (empty($translation->default_value)) {
            throw new \RuntimeException('No source text available for translation.');
        }

        $sourceLocale = Language::defaultLocale();
        $targetLocale = $translation->locale;

        if ($sourceLocale === $targetLocale) {
            // Source and target are the same, just use the default value
            $translated = $translation->default_value;
        } else {
            $translated = $this->translateWithLlm(
                $translation->default_value,
                $sourceLocale,
                $targetLocale,
                $translation->namespace->namespace,
                $translation->key,
            );
        }

        $oldValue = $translation->value;
        $translation->update([
            'value' => $translated,
            'is_auto_translated' => true,
            'updated_by' => $userId,
        ]);

        $translation->recordHistory('auto_translated', $oldValue, $translated, $userId);

        return $translation->fresh()->load('namespace');
    }

    /**
     * 批量翻译指定语言下所有缺失的翻译
     */
    public function translateMissing(
        string $locale,
        ?int $namespaceId = null,
        ?int $userId = null,
        int $chunkSize = 50,
    ): array {
        $sourceLocale = Language::defaultLocale();

        if ($sourceLocale === $locale) {
            return ['total' => 0, 'translated' => 0, 'failed' => 0, 'skipped' => 0, 'message' => __('app.translation_engine.source_target_same')];
        }

        $results = ['total' => 0, 'translated' => 0, 'failed' => 0, 'skipped' => 0];

        // Get source language translations as reference
        $sourceTranslations = Translation::where('locale', $sourceLocale)
            ->whereNotNull('default_value')
            ->get()
            ->keyBy(fn($t) => $t->namespace_id . ':' . $t->key);

        $query = Translation::with('namespace')
            ->where('locale', $locale)
            ->whereNull('value')
            // Only translate entries that have a source counterpart
            ->whereIn(
                DB::raw("(namespace_id, `key`)"),
                $sourceTranslations->map(fn($t) => [$t->namespace_id, $t->key])->toArray()
            );

        if ($namespaceId) {
            $query->where('namespace_id', $namespaceId);
        }

        $results['total'] = (clone $query)->count();

        $query->chunk($chunkSize, function ($translations) use ($sourceLocale, $locale, $userId, $sourceTranslations, &$results) {
            foreach ($translations as $t) {
                try {
                    $sourceKey = $t->namespace_id . ':' . $t->key;
                    $sourceText = $sourceTranslations[$sourceKey]->default_value ?? null;

                    if (empty($sourceText)) {
                        $results['skipped']++;
                        continue;
                    }

                    // Check translation memory
                    if ($cached = $this->getFromMemory($sourceText, $locale)) {
                        $oldValue = $t->value;
                        $t->update([
                            'value' => $cached,
                            'is_auto_translated' => true,
                            'updated_by' => $userId,
                        ]);
                        $t->recordHistory('auto_translated', $oldValue, $cached, $userId);
                        $results['translated']++;
                        continue;
                    }

                    $translated = $this->translateWithLlm(
                        $sourceText,
                        $sourceLocale,
                        $locale,
                        $t->namespace->namespace,
                        $t->key,
                    );

                    $this->saveToMemory($sourceText, $locale, $translated);

                    $oldValue = $t->value;
                    $t->update([
                        'value' => $translated,
                        'is_auto_translated' => true,
                        'updated_by' => $userId,
                    ]);
                    $t->recordHistory('auto_translated', $oldValue, $translated, $userId);
                    $results['translated']++;
                } catch (\Exception $e) {
                    Log::warning("Translation failed for translation ID {$t->id}: {$e->getMessage()}");
                    $results['failed']++;
                }
            }
        });

        return $results;
    }

    /**
     * 使用 LLM 翻译单条文本
     */
    public function translateWithLlm(
        string $text,
        string $sourceLocale,
        string $targetLocale,
        ?string $namespace = null,
        ?string $key = null,
    ): string {
        if (empty(trim($text))) {
            return $text;
        }

        if ($sourceLocale === $targetLocale) {
            return $text;
        }

        $sourceName = $this->getLanguageName($sourceLocale);
        $targetName = $this->getLanguageName($targetLocale);
        $context = $this->buildTranslationContext($namespace, $key);

        $systemPrompt = $this->buildSystemPrompt($sourceName, $targetName, $context);
        $userPrompt = $text;

        try {
            $result = $this->llm->chat(
                messages: [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                options: [
                    'temperature' => 0.3,
                    'max_tokens' => min((int) (mb_strlen($text) * 2.5) + 100, 4000),
                ],
                function: 'translation',
            );

            $translated = trim($result['content'] ?? '');

            if (empty($translated)) {
                throw new \RuntimeException('LLM returned empty translation');
            }

            return $translated;
        } catch (\Exception $e) {
            Log::error("LLM translation failed: {$e->getMessage()}", [
                'text' => mb_substr($text, 0, 200),
                'from' => $sourceLocale,
                'to' => $targetLocale,
            ]);

            // Fallback: try simple translate as last resort
            return $this->simpleTranslate($text, $sourceLocale, $targetLocale);
        }
    }

    /**
     * 批量翻译文本数组（用于外部调用）
     */
    public function translateBatch(array $items, string $targetLocale): array
    {
        $sourceLocale = Language::defaultLocale();
        $results = [];

        foreach ($items as $key => $text) {
            try {
                $results[$key] = $this->translateWithLlm($text, $sourceLocale, $targetLocale);
            } catch (\Exception) {
                $results[$key] = $text;
            }
        }

        return $results;
    }

    /**
     * 评估翻译质量（返回质量评分）
     */
    public function assessQuality(Translation $translation): array
    {
        $sourceLocale = Language::defaultLocale();
        $targetLocale = $translation->locale;

        if ($sourceLocale === $targetLocale || empty($translation->default_value) || empty($translation->value)) {
            return ['score' => 0, 'issues' => [__('app.translation_engine.missing_source_or_target')]];
        }

        $sourceText = $translation->default_value;
        $targetText = $translation->value;

        // Length ratio check (very short translations should be similar length)
        $sourceLen = mb_strlen($sourceText);
        $targetLen = mb_strlen($targetText);
        $ratio = $sourceLen > 0 ? $targetLen / $sourceLen : 1;

        $issues = [];
        $score = 100;

        // Check 1: Length ratio sanity
        if ($ratio < 0.3 || $ratio > 3.0) {
            $score -= 20;
            $issues[] = __('app.translation_engine.length_ratio_warning', ['ratio' => round($ratio, 2)]);
        }

        // Check 2: Check for source text leaked in target
        $sourceWords = $this->getSignificantWords($sourceText, $sourceLocale);
        $targetWords = $this->getSignificantWords($targetText, $targetLocale);
        $overlap = array_intersect($sourceWords, $targetWords);
        $overlapRatio = count($sourceWords) > 0 ? count($overlap) / count($sourceWords) : 0;

        if ($overlapRatio > 0.8 && $sourceLocale !== $targetLocale) {
            $score -= 30;
            $issues[] = __('app.translation_engine.untranslated_warning');
        }

        // Check 3: Variable placeholders preserved
        preg_match_all('/\{[^}]+\}|%[ds]|:[a-zA-Z_]+/', $sourceText, $sourceVars);
        preg_match_all('/\{[^}]+\}|%[ds]|:[a-zA-Z_]+/', $targetText, $targetVars);
        $missingVars = array_diff($sourceVars[0], $targetVars[0]);
        if (!empty($missingVars)) {
            $score -= 15;
            $issues[] = __('app.translation_engine.missing_placeholders', ['vars' => implode(', ', $missingVars)]);
        }

        return [
            'score' => max(0, $score),
            'issues' => $issues,
            'length_ratio' => round($ratio, 2),
            'source_length' => $sourceLen,
            'target_length' => $targetLen,
        ];
    }

    /**
     * 获取翻译记忆统计
     */
    public function getMemoryStats(): array
    {
        $total = Translation::where('is_auto_translated', true)->count();
        $uniqueSources = Translation::where('is_auto_translated', true)
            ->whereNotNull('default_value')
            ->distinct('default_value')
            ->count('default_value');

        return [
            'total_auto_translated' => $total,
            'unique_source_texts' => $uniqueSources,
            'memory_efficiency' => $total > 0 ? round((1 - $uniqueSources / $total) * 100, 1) : 0,
        ];
    }

    // ── Protected helpers ──

    protected function buildSystemPrompt(string $sourceName, string $targetName, string $context): string
    {
        // 尝试从 Prompt 模板获取翻译 Prompt
        try {
            $promptService = app(PromptTemplateService::class);
            $rendered = $promptService->renderByCategory('translation', [
                'source_lang' => $sourceName,
                'target_lang' => $targetName,
                'text' => '{text}',
            ]);
            if (!empty($rendered)) {
                // 替换最终占位符，追加上下文
                $rendered = str_replace('{text}', '', $rendered);
                return $rendered . "\n{$context}\n\n翻译以下内容：\n";
            }
        } catch (\Throwable $e) {
            // 降级到默认 prompt
        }

        return <<<PROMPT
你是一个专业的翻译引擎。你的任务是将文本从 {$sourceName} 翻译成 {$targetName}。

翻译要求：
1. 保持原文的语气和风格
2. 只返回翻译结果，不要添加任何解释、注释或额外内容
3. 保留所有 HTML 标签、Markdown 格式和占位符（如 {variable}、%s、:param 等）
4. 根据上下文使用合适的术语
5. 如果原文是技术术语或专有名词，保留原文并在括号内标注翻译
{$context}

翻译以下内容：
PROMPT;
    }

    protected function buildTranslationContext(?string $namespace, ?string $key): string
    {
        $parts = [];

        if ($namespace) {
            $parts[] = "命名空间: {$namespace}";
        }
        if ($key) {
            $parts[] = "翻译键: {$key}";

            // Try to infer context from key
            if (str_contains($key, 'error')) {
                $parts[] = "上下文提示: 这是一个错误消息，请保持简洁明了";
            } elseif (str_contains($key, 'title') || str_contains($key, 'heading')) {
                $parts[] = "上下文提示: 这是一个标题，请使用标题式语言";
            } elseif (str_contains($key, 'desc') || str_contains($key, 'description') || str_contains($key, 'help')) {
                $parts[] = "上下文提示: 这是一个描述/帮助文本，请使用清晰易懂的语言";
            } elseif (str_contains($key, 'btn') || str_contains($key, 'button') || str_contains($key, 'action')) {
                $parts[] = "上下文提示: 这是一个按钮/操作标签，请使用简短的祈使句";
            } elseif (str_contains($key, 'label') || str_contains($key, 'field')) {
                $parts[] = "上下文提示: 这是一个表单标签，请保持简短";
            } elseif (str_contains($key, 'success') || str_contains($key, 'success_msg')) {
                $parts[] = "上下文提示: 这是一个成功消息";
            } elseif (str_contains($key, 'warn') || str_contains($key, 'warning')) {
                $parts[] = "上下文提示: 这是一个警告消息";
            } elseif (str_contains($key, 'placeholder')) {
                $parts[] = "上下文提示: 这是一个输入框占位文本";
            } elseif (str_contains($key, 'menu') || str_contains($key, 'nav')) {
                $parts[] = "上下文提示: 这是一个导航菜单项";
            }
        }

        return !empty($parts) ? implode("\n", $parts) . "\n" : '';
    }

    protected function getLanguageName(string $locale): string
    {
        $names = [
            'zh_CN' => '简体中文',
            'zh-TW' => '繁体中文',
            'en' => 'English',
            'ja' => '日本語',
            'ko' => '한국어',
            'fr' => 'Français',
            'de' => 'Deutsch',
            'es' => 'Español',
            'pt' => 'Português',
            'ru' => 'Русский',
            'ar' => 'العربية',
            'th' => 'ไทย',
            'vi' => 'Tiếng Việt',
        ];

        return $names[$locale] ?? $locale;
    }

    protected function getSignificantWords(string $text, string $locale): array
    {
        // Remove common punctuation and split
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);
        $words = preg_split('/\s+/', $clean);

        // Filter out very common words (stop words)
        $stopWords = $locale === 'zh_CN'
            ? ['的', '了', '在', '是', '我', '有', '和', '就', '不', '人', '都', '一', '一个', '上', '也', '很', '到', '说', '要', '去', '你', '会', '着', '没有', '看', '好', '自己', '这']
            : ['the', 'a', 'an', 'in', 'on', 'at', 'to', 'for', 'of', 'and', 'or', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'can', 'could', 'may', 'might', 'shall', 'should', 'it', 'its', 'this', 'that', 'these', 'those'];

        return array_values(array_filter(array_map('trim', $words), fn($w) => !in_array(mb_strtolower($w), $stopWords) && mb_strlen($w) > 1));
    }

    protected function getFromMemory(string $sourceText, string $locale): ?string
    {
        $key = md5($sourceText) . ':' . $locale;

        if (isset($this->translationMemory[$key])) {
            return $this->translationMemory[$key];
        }

        // Check database for existing translations of the same source text
        $existing = Translation::where('locale', $locale)
            ->where('default_value', $sourceText)
            ->whereNotNull('value')
            ->first();

        if ($existing) {
            $this->translationMemory[$key] = $existing->value;
            return $existing->value;
        }

        return null;
    }

    protected function saveToMemory(string $sourceText, string $locale, string $translated): void
    {
        $key = md5($sourceText) . ':' . $locale;
        $this->translationMemory[$key] = $translated;
    }

    /**
     * 兜底简单翻译（当 LLM 不可用时）
     */
    protected function simpleTranslate(string $text, string $from, string $to): string
    {
        if ($from === $to) {
            return $text;
        }

        // Basic known translations for common terms
        $glossary = [
            'zh_CN' => [
                'Save' => '保存',
                'Cancel' => '取消',
                'Delete' => '删除',
                'Edit' => '编辑',
                'Create' => '创建',
                'Update' => '更新',
                'Search' => '搜索',
                'Reset' => '重置',
                'Submit' => '提交',
                'Confirm' => '确认',
                'Close' => '关闭',
                'Open' => '打开',
                'Back' => '返回',
                'Next' => '下一步',
                'Previous' => '上一步',
                'Done' => '完成',
                'Error' => '错误',
                'Warning' => '警告',
                'Info' => '信息',
                'Success' => '成功',
                'Failed' => '失败',
                'Loading' => '加载中',
                'No data' => '暂无数据',
                'Active' => '活跃',
                'Inactive' => '不活跃',
                'Pending' => '待处理',
                'Expired' => '已过期',
                'Enabled' => '已启用',
                'Disabled' => '已禁用',
                'Name' => '名称',
                'Type' => '类型',
                'Status' => '状态',
                'Actions' => '操作',
                'Details' => '详情',
            ],
        ];

        if (isset($glossary[$to][$text])) {
            return $glossary[$to][$text];
        }

        // As last resort, return the source text
        return $text;
    }
}
