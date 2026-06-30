<?php

namespace App\Console\Commands;

use App\Models\Language;
use App\Models\Translation;
use App\Services\TranslationEngineService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoTranslateCommand extends Command
{
    protected $signature = 'i18n:auto-translate
        {locale? : 目标语言代码（留空则翻译所有非源语言）}
        {--namespace= : 限定命名空间 ID}
        {--dry-run : 仅预览将要翻译的条目数，不实际翻译}
        {--chunk=100 : 每次处理的条目数}
        {--force : 强制重新翻译已存在的条目}';

    protected $description = '自动翻译缺失的翻译条目（使用 LLM）';

    public function handle(TranslationEngineService $engine): int
    {
        $sourceLocale = Language::defaultLocale();
        $targetLocale = $this->argument('locale');
        $namespaceId = $this->option('namespace');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $chunk = (int) $this->option('chunk');

        $locales = $targetLocale
            ? [Language::where('locale', $targetLocale)->firstOrFail()]
            : Language::where('is_active', true)->where('locale', '!=', $sourceLocale)->get();

        if ($locales->isEmpty()) {
            $this->warn('没有找到需要翻译的目标语言。');
            return 0;
        }

        $totalTranslated = 0;
        $totalFailed = 0;
        $totalSkipped = 0;

        foreach ($locales as $language) {
            $this->newLine();
            $this->info("处理语言: {$language->name} ({$language->locale})");

            $query = Translation::where('locale', $language->locale)
                ->whereNull('value');

            if (!$force) {
                $query->where('is_auto_translated', false);
            }

            if ($namespaceId) {
                $query->where('namespace_id', $namespaceId);
            }

            $count = $query->count();

            if ($count === 0) {
                $this->info("  └─ 没有需要翻译的条目。");
                continue;
            }

            if ($dryRun) {
                $this->line("  └─ 将翻译 {$count} 条缺失条目");
                $totalSkipped += $count;
                continue;
            }

            $bar = $this->output->createProgressBar($count);
            $bar->start();

            $query->chunk($chunk, function ($translations) use ($engine, $language, $bar, &$totalTranslated, &$totalFailed) {
                foreach ($translations as $t) {
                    try {
                        $engine->translateSingle($t->id);
                        $totalTranslated++;
                    } catch (\Exception $e) {
                        Log::error("Auto-translate failed for ID {$t->id}: {$e->getMessage()}");
                        $totalFailed++;
                    }
                    $bar->advance();
                }
            });

            $bar->finish();
            $this->newLine();
            $this->info("  └─ 完成: {$count} 条");
        }

        $this->newLine();
        $this->table(
            ['项目', '数量'],
            [
                ['已翻译', $totalTranslated],
                ['失败', $totalFailed],
                ['跳过', $totalSkipped],
            ]
        );

        return 0;
    }
}
