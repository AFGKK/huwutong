<?php

namespace App\Services;

use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WCAG 2.1 AA 无障碍合规管理服务 + 无障碍 AI 辅助服务
 *
 * M3-54
 * - 合规声明版本管理
 * - 合规扫描和报告生成
 * - 用户无障碍偏好管理
 * - 已知限制追踪
 * - AI 图片 ALT 文本生成
 * - 消息/会话无障碍摘要
 * - 文字转语音
 */
class A11yService
{
    protected LlmService $llm;

    public function __construct(LlmService $llm)
    {
        $this->llm = $llm;
    }

    /**
     * WCAG 2.1 AA 成功准则定义
     */
    public function getGuidelines(): array
    {
        return [
            ['id' => '1.1.1', 'name' => __('app.a11y.guidelines.1_1_1.name'), 'level' => 'A', 'description' => __('app.a11y.guidelines.1_1_1.description'), 'status' => 'compliant'],
            ['id' => '1.2.1', 'name' => __('app.a11y.guidelines.1_2_1.name'), 'level' => 'A', 'description' => __('app.a11y.guidelines.1_2_1.description'), 'status' => 'compliant'],
            ['id' => '1.2.2', 'name' => __('app.a11y.guidelines.1_2_2.name'), 'level' => 'A', 'description' => __('app.a11y.guidelines.1_2_2.description'), 'status' => 'compliant'],
            ['id' => '1.2.3', 'name' => __('app.a11y.guidelines.1_2_3.name'), 'level' => 'A', 'description' => __('app.a11y.guidelines.1_2_3.description'), 'status' => 'compliant'],
            ['id' => '1.2.4', 'name' => __('app.a11y.guidelines.1_2_4.name'), 'level' => 'AA', 'description' => __('app.a11y.guidelines.1_2_4.description'), 'status' => 'not_applicable'],
            ['id' => '1.2.5', 'name' => __('app.a11y.guidelines.1_2_5.name'), 'level' => 'AA', 'description' => __('app.a11y.guidelines.1_2_5.description'), 'status' => 'not_applicable'],
            ['id' => '1.3.1', 'name' => __('app.a11y.guidelines.1_3_1.name'), 'level' => 'A', 'description' => __('app.a11y.guidelines.1_3_1.description'), 'status' => 'compliant'],
            ['id' => '1.3.2', 'name' => __('app.a11y.guidelines.1_3_2.name'), 'level' => 'A', 'description' => __('app.a11y.guidelines.1_3_2.description'), 'status' => 'compliant'],
            ['id' => '1.3.3', 'name' => __('app.a11y.guidelines.1_3_3.name'), 'level' => 'A', 'description' => __('app.a11y.guidelines.1_3_3.description'), 'status' => 'compliant'],
            ['id' => '1.3.4', 'name' => __('app.a11y.guidelines.1_3_4.name'), 'level' => 'AA', 'description' => __('app.a11y.guidelines.1_3_4.description'), 'status' => 'compliant'],
            ['id' => '1.3.5', 'name' => __('app.a11y.guidelines.1_3_5.name'), 'level' => 'AA', 'description' => __('app.a11y.guidelines.1_3_5.description'), 'status' => 'needs_work'],
            ['id' => '1.4.1', 'name' => __('app.a11y.guidelines.1_4_1.name'), 'level' => 'AA', 'description' => __('app.a11y.guidelines.1_4_1.description'), 'status' => 'compliant'],
            ['id' => '1.4.2', 'name' => __('app.a11y.guidelines.1_4_2.name'), 'level' => 'A', 'description' => __('app.a11y.guidelines.1_4_2.description'), 'status' => 'compliant'],
            ['id' => '1.4.3', 'name' => __('app.a11y.guidelines.1_4_3.name'), 'level' => 'AA', 'description' => __('app.a11y.guidelines.1_4_3.description'), 'status' => 'compliant'],
            ['id' => '1.4.4', 'name' => __('app.a11y.guidelines.1_4_4.name'), 'level' => 'AA', 'description' => __('app.a11y.guidelines.1_4_4.description'), 'status' => 'compliant'],
            ['id' => '1.4.5', 'name' => __('app.a11y.guidelines.1_4_5.name'), 'level' => 'AA', 'description' => __('app.a11y.guidelines.1_4_5.description'), 'status' => 'compliant'],
            ['id' => '1.4.10', 'name' => __('app.a11y.guidelines.1_4_10.name'), 'level' => 'AA', 'description' => __('app.a11y.guidelines.1_4_10.description'), 'status' => 'needs_work'],
            ['id' => '1.4.11', 'name' => __('app.a11y.guidelines.1_4_11.name'), 'level' => 'AA', 'description' => __('app.a11y.guidelines.1_4_11.description'), 'status' => 'compliant'],
            ['id' => '1.4.12', 'name' => __('app.a11y.guidelines.1_4_12.name'), 'level' => 'AA', 'description' => __('app.a11y.guidelines.1_4_12.description'), 'status' => 'compliant'],
            ['id' => '1.4.13', 'name' => __('app.a11y.guidelines.1_4_13.name'), 'level' => 'AA', 'description' => __('app.a11y.guidelines.1_4_13.description'), 'status' => 'needs_work'],
            ['id' => '2.1.1', 'name' => __('app.a11y.guidelines.2_1_1.name'), 'level' => 'A', 'description' => __('app.a11y.guidelines.2_1_1.description'), 'status' => 'compliant'],
            ['id' => '2.1.2', 'name' => __('app.a11y.guidelines.2_1_2.name'), 'level' => 'A', 'description' => __('app.a11y.guidelines.2_1_2.description'), 'status' => 'compliant'],
            ['id' => '2.1.4', 'name' => __('app.a11y.guidelines.2_1_4.name'), 'level' => 'A', 'description' => __('app.a11y.guidelines.2_1_4.description'), 'status' => 'compliant'],
            ['id' => '2.2.1', 'name' => __('app.a11y.guidelines.2_2_1.name'), 'level' => 'A', 'description' => __('app.a11y.guidelines.2_2_1.description'), 'status' => 'compliant'],
            ['id' => '2.2.2', 'name' => __('app.a11y.guidelines.2_2_2.name'), 'level' => 'A', 'description' => __('app.a11y.guidelines.2_2_2.description'), 'status' => 'compliant'],
            ['id' => '2.3.1', 'name' => __('app.a11y.guidelines.2_3_1.name'), 'level' => 'A', 'description' => __('app.a11y.guidelines.2_3_1.description'), 'status' => 'compliant'],
            ['id' => '2.4.1', 'name' => __('app.a11y.guidelines.2_4_1.name'), 'level' => 'A', 'description' => __('app.a11y.guidelines.2_4_1.description'), 'status' => 'compliant'],
            ['id' => '2.4.2', 'name' => __('app.a11y.guidelines.2_4_2.name'), 'level' => 'A', 'description' => __('app.a11y.guidelines.2_4_2.description'), 'status' => 'compliant'],
            ['id' => '2.4.3', 'name' => __('app.a11y.guidelines.2_4_3.name'), 'level' => 'A', 'description' => __('app.a11y.guidelines.2_4_3.description'), 'status' => 'compliant'],
            ['id' => '2.4.4', 'name' => __('app.a11y.guidelines.2_4_4.name'), 'level' => 'A', 'description' => __('app.a11y.guidelines.2_4_4.description'), 'status' => 'compliant'],
            ['id' => '2.4.5', 'name' => __('app.a11y.guidelines.2_4_5.name'), 'level' => 'AA', 'description' => __('app.a11y.guidelines.2_4_5.description'), 'status' => 'compliant'],
            ['id' => '2.4.6', 'name' => __('app.a11y.guidelines.2_4_6.name'), 'level' => 'AA', 'description' => __('app.a11y.guidelines.2_4_6.description'), 'status' => 'compliant'],
            ['id' => '2.4.7', 'name' => __('app.a11y.guidelines.2_4_7.name'), 'level' => 'AA', 'description' => __('app.a11y.guidelines.2_4_7.description'), 'status' => 'compliant'],
            ['id' => '2.5.1', 'name' => __('app.a11y.guidelines.2_5_1.name'), 'level' => 'A', 'description' => __('app.a11y.guidelines.2_5_1.description'), 'status' => 'compliant'],
            ['id' => '2.5.2', 'name' => __('app.a11y.guidelines.2_5_2.name'), 'level' => 'A', 'description' => __('app.a11y.guidelines.2_5_2.description'), 'status' => 'compliant'],
            ['id' => '2.5.3', 'name' => __('app.a11y.guidelines.2_5_3.name'), 'level' => 'A', 'description' => __('app.a11y.guidelines.2_5_3.description'), 'status' => 'needs_work'],
            ['id' => '2.5.4', 'name' => __('app.a11y.guidelines.2_5_4.name'), 'level' => 'A', 'description' => __('app.a11y.guidelines.2_5_4.description'), 'status' => 'not_applicable'],
            ['id' => '3.1.1', 'name' => __('app.a11y.guidelines.3_1_1.name'), 'level' => 'A', 'description' => __('app.a11y.guidelines.3_1_1.description'), 'status' => 'compliant'],
            ['id' => '3.1.2', 'name' => __('app.a11y.guidelines.3_1_2.name'), 'level' => 'AA', 'description' => __('app.a11y.guidelines.3_1_2.description'), 'status' => 'compliant'],
            ['id' => '3.2.1', 'name' => __('app.a11y.guidelines.3_2_1.name'), 'level' => 'A', 'description' => __('app.a11y.guidelines.3_2_1.description'), 'status' => 'compliant'],
            ['id' => '3.2.2', 'name' => __('app.a11y.guidelines.3_2_2.name'), 'level' => 'A', 'description' => __('app.a11y.guidelines.3_2_2.description'), 'status' => 'compliant'],
            ['id' => '3.2.3', 'name' => __('app.a11y.guidelines.3_2_3.name'), 'level' => 'AA', 'description' => __('app.a11y.guidelines.3_2_3.description'), 'status' => 'compliant'],
            ['id' => '3.2.4', 'name' => __('app.a11y.guidelines.3_2_4.name'), 'level' => 'AA', 'description' => __('app.a11y.guidelines.3_2_4.description'), 'status' => 'compliant'],
            ['id' => '3.3.1', 'name' => __('app.a11y.guidelines.3_3_1.name'), 'level' => 'A', 'description' => __('app.a11y.guidelines.3_3_1.description'), 'status' => 'compliant'],
            ['id' => '3.3.2', 'name' => __('app.a11y.guidelines.3_3_2.name'), 'level' => 'A', 'description' => __('app.a11y.guidelines.3_3_2.description'), 'status' => 'compliant'],
            ['id' => '3.3.3', 'name' => __('app.a11y.guidelines.3_3_3.name'), 'level' => 'AA', 'description' => __('app.a11y.guidelines.3_3_3.description'), 'status' => 'needs_work'],
            ['id' => '3.3.4', 'name' => __('app.a11y.guidelines.3_3_4.name'), 'level' => 'AA', 'description' => __('app.a11y.guidelines.3_3_4.description'), 'status' => 'compliant'],
            ['id' => '4.1.1', 'name' => __('app.a11y.guidelines.4_1_1.name'), 'level' => 'A', 'description' => __('app.a11y.guidelines.4_1_1.description'), 'status' => 'compliant'],
            ['id' => '4.1.2', 'name' => __('app.a11y.guidelines.4_1_2.name'), 'level' => 'A', 'description' => __('app.a11y.guidelines.4_1_2.description'), 'status' => 'compliant'],
            ['id' => '4.1.3', 'name' => __('app.a11y.guidelines.4_1_3.name'), 'level' => 'AA', 'description' => __('app.a11y.guidelines.4_1_3.description'), 'status' => 'compliant'],
        ];
    }

    /**
     * 获取合规声明统计
     */
    public function getComplianceStats(): array
    {
        $guidelines = $this->getGuidelines();

        $total = count($guidelines);
        $compliant = count(array_filter($guidelines, fn($g) => $g['status'] === 'compliant'));
        $needsWork = count(array_filter($guidelines, fn($g) => $g['status'] === 'needs_work'));
        $notApplicable = count(array_filter($guidelines, fn($g) => $g['status'] === 'not_applicable'));

        $passRate = $total - $notApplicable > 0
            ? round($compliant / ($total - $notApplicable) * 100, 1)
            : 0;

        return compact('total', 'compliant', 'needsWork', 'notApplicable', 'passRate');
    }

    /**
     * 获取已知限制
     */
    public function getKnownLimitations(): array
    {
        return [
            [
                'area' => __('app.a11y.limitations.0.area'),
                'description' => __('app.a11y.limitations.0.description'),
                'severity' => 'medium',
                'workaround' => __('app.a11y.limitations.0.workaround'),
                'planned_fix' => __('app.a11y.limitations.0.planned_fix'),
            ],
            [
                'area' => __('app.a11y.limitations.1.area'),
                'description' => __('app.a11y.limitations.1.description'),
                'severity' => 'low',
                'workaround' => __('app.a11y.limitations.1.workaround'),
                'planned_fix' => __('app.a11y.limitations.1.planned_fix'),
            ],
            [
                'area' => __('app.a11y.limitations.2.area'),
                'description' => __('app.a11y.limitations.2.description'),
                'severity' => 'low',
                'workaround' => __('app.a11y.limitations.2.workaround'),
                'planned_fix' => __('app.a11y.limitations.2.planned_fix'),
            ],
            [
                'area' => __('app.a11y.limitations.3.area'),
                'description' => __('app.a11y.limitations.3.description'),
                'severity' => 'medium',
                'workaround' => __('app.a11y.limitations.3.workaround'),
                'planned_fix' => __('app.a11y.limitations.3.planned_fix'),
            ],
        ];
    }

    /**
     * 获取用户无障碍偏好
     */
    public function getUserPreferences(int $userId): array
    {
        $user = User::find($userId);
        if (!$user) return [];

        $prefs = $user->preferences['accessibility'] ?? [];

        return [
            'reduced_motion' => $prefs['reduced_motion'] ?? false,
            'high_contrast' => $prefs['high_contrast'] ?? false,
            'font_size' => $prefs['font_size'] ?? 'normal',
            'screen_reader_optimized' => $prefs['screen_reader_optimized'] ?? false,
        ];
    }

    /**
     * 保存用户无障碍偏好
     */
    public function saveUserPreferences(int $userId, array $prefs): array
    {
        $user = User::findOrFail($userId);
        $existing = $user->preferences ?? [];
        $existing['accessibility'] = array_merge(
            $existing['accessibility'] ?? [],
            array_intersect_key($prefs, array_flip([
                'reduced_motion', 'high_contrast', 'font_size', 'screen_reader_optimized',
            ]))
        );

        $user->preferences = $existing;
        $user->save();

        return $existing['accessibility'];
    }

    /**
     * 对比度检查工具
     */
    public function checkContrast(string $foreground, string $background): array
    {
        $hexToRgb = function (string $hex): array {
            $hex = ltrim($hex, '#');
            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }
            return [
                hexdec(substr($hex, 0, 2)),
                hexdec(substr($hex, 2, 2)),
                hexdec(substr($hex, 4, 2)),
            ];
        };

        $relativeLuminance = function (array $rgb): float {
            $vals = array_map(function ($c) {
                $c = $c / 255;
                return $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
            }, $rgb);
            return 0.2126 * $vals[0] + 0.7152 * $vals[1] + 0.0722 * $vals[2];
        };

        $fgRgb = $hexToRgb($foreground);
        $bgRgb = $hexToRgb($background);

        $fgLum = $relativeLuminance($fgRgb);
        $bgLum = $relativeLuminance($bgRgb);

        $ratio = ($fgLum + 0.05) / ($bgLum + 0.05);
        if ($ratio < 1) $ratio = 1 / $ratio;

        $passesAA = $ratio >= 4.5;
        $passesAALarge = $ratio >= 3.0;
        $passesAAA = $ratio >= 7.0;

        return [
            'foreground' => $foreground,
            'background' => $background,
            'ratio' => round($ratio, 2),
            'passes_AA' => $passesAA,
            'passes_AA_large' => $passesAALarge,
            'passes_AAA' => $passesAAA,
            'rating' => $passesAAA ? 'AAA' : ($passesAA ? 'AA' : ($passesAALarge ? __('app.a11y.rating_AA_large') : 'FAIL')),
        ];
    }

    /**
     * 生成无障碍合规报告
     */
    public function generateReport(): array
    {
        $stats = $this->getComplianceStats();
        $guidelines = $this->getGuidelines();
        $limitations = $this->getKnownLimitations();

        return [
            'generated_at' => now()->toIso8601String(),
            'standard' => 'WCAG 2.1 AA',
            'scope' => __('app.a11y.report_scope'),
            'summary' => [
                'total_criteria' => $stats['total'],
                'compliant' => $stats['compliant'],
                'needs_work' => $stats['needsWork'],
                'not_applicable' => $stats['notApplicable'],
                'pass_rate' => $stats['passRate'] . '%',
            ],
            'compliance_by_level' => [
                'A' => count(array_filter($guidelines, fn($g) => $g['level'] === 'A' && $g['status'] === 'compliant')),
                'AA' => count(array_filter($guidelines, fn($g) => $g['level'] === 'AA' && $g['status'] === 'compliant')),
            ],
            'non_compliant_items' => array_values(array_filter($guidelines, fn($g) => $g['status'] === 'needs_work')),
            'known_limitations' => $limitations,
        ];
    }

    // ── 无障碍 AI 辅助（原 AccessibilityService 方法） ──

    /**
     * 为图片生成 ALT 描述文本
     */
    public function generateImageAlt(string $imageUrl): string
    {
        $cacheKey = 'img_alt_' . md5($imageUrl);

        return Cache::remember($cacheKey, 86400 * 30, function () use ($imageUrl) {
            try {
                $result = $this->llm->chat([
                    ['role' => 'system', 'content' => '你是一个图片描述助手，为视障用户生成简洁的图片描述。
要求：
- 用一句话描述图片核心内容（15-30字）
- 描述主体、动作、场景
- 保持客观，不添加推测
- 用中文
- 如果图片包含文字，提取关键文字'],
                    ['role' => 'user', 'content' => "请描述这张图片：{$imageUrl}"],
                ], ['temperature' => 0.2, 'max_tokens' => 150], 'a11y_image_alt');

                return $result['content'] ?? '图片';
            } catch (\Throwable $e) {
                Log::warning('Image ALT generation failed', ['error' => $e->getMessage()]);
                return '图片';
            }
        });
    }

    /**
     * 为聊天消息生成摘要（供屏幕阅读器快速浏览）
     */
    public function summarizeMessage(ConversationMessage $msg): string
    {
        $sender = $msg->sender?->name ?? '用户';
        $type = $msg->message_type ?? 'text';
        $time = $msg->created_at?->format('H:i') ?? '';

        return match ($type) {
            'text' => "{$sender} 在 {$time} 说：{$msg->content}",
            'image' => "{$sender} 在 {$time} 发送了一张图片：" . ($msg->metadata['alt_text'] ?? ''),
            'voice' => "{$sender} 在 {$time} 发送了一条语音消息",
            'file' => "{$sender} 在 {$time} 发送了文件：" . ($msg->metadata['file_name'] ?? '文件'),
            'forward' => "{$sender} 在 {$time} 转发了消息",
            'sticker' => "{$sender} 在 {$time} 发送了一个贴纸",
            default => "{$sender} 在 {$time} 发送了一条 {$type} 类型消息",
        };
    }

    /**
     * 为整段会话生成无障碍摘要
     */
    public function summarizeConversation(int $convId, int $limit = 20): array
    {
        $messages = ConversationMessage::where('conversation_id', $convId)
            ->whereNull('deleted_at')
            ->latest()
            ->take($limit)
            ->get()
            ->reverse();

        $summaries = [];
        foreach ($messages as $msg) {
            $summaries[] = $this->summarizeMessage($msg);
        }

        return [
            'total' => count($summaries),
            'items' => $summaries,
            'full_text' => implode("\n", $summaries),
        ];
    }

    /**
     * 生成图片的详细无障碍描述（长文，用于独立查看）
     */
    public function describeImageDetail(string $imageUrl): array
    {
        try {
            $result = $this->llm->chat([
                ['role' => 'system', 'content' => '你是一个专业的无障碍描述助手。为视障用户生成详细的图片描述，包含：
1. 图片类型（照片/插画/图表/截图等）
2. 主体描述
3. 颜色和构图
4. 文字内容（如有）
5. 整体氛围
使用中文，描述要准确、客观。'],
                ['role' => 'user', 'content' => "请详细描述这张图片：{$imageUrl}"],
            ], ['temperature' => 0.3, 'max_tokens' => 500], 'a11y_image_detail');

            $description = $result['content'] ?? '无法生成描述';

            return [
                'description' => $description,
                'short_alt' => $this->generateImageAlt($imageUrl),
            ];
        } catch (\Throwable $e) {
            return [
                'description' => '图片描述暂时不可用',
                'short_alt' => '图片',
            ];
        }
    }

    /**
     * 文字转语音（调用现有 TTS API）
     */
    public function textToSpeech(string $text): ?string
    {
        try {
            $asr = app(AsrService::class);
            if (method_exists($asr, 'synthesize')) {
                return $asr->synthesize($text);
            }
            return null;
        } catch (\Throwable $e) {
            Log::warning('TTS failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
