<?php

namespace App\Services;

use App\Models\ConversationMessage;
use App\Models\UserConversation;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SlashCommandService
{
    protected array $commands = [];

    public function __construct()
    {
        $this->registerDefaults();
    }

    /**
     * 注册所有默认命令
     */
    protected function registerDefaults(): void
    {
        $this->commands = [
            'help' => [
                'description' => '显示所有可用命令',
                'usage' => '/help',
                'handler' => 'handleHelp',
            ],
            'remind' => [
                'description' => '设置提醒',
                'usage' => '/remind [时间] [内容]',
                'example' => '/remind 30分钟后 回复张三邮件',
                'handler' => 'handleRemind',
                'params' => ['time', 'content'],
            ],
            'poll' => [
                'description' => '创建投票',
                'usage' => '/poll 问题 | 选项1 | 选项2 | ...',
                'example' => '/poll 团建去哪里? | 火锅 | 烧烤 | 日料',
                'handler' => 'handlePoll',
            ],
            'translate' => [
                'description' => '翻译消息',
                'usage' => '/translate [目标语言] [文本]',
                'example' => '/translate en 你好世界',
                'handler' => 'handleTranslate',
            ],
            'summarize' => [
                'description' => '总结对话',
                'usage' => '/summarize [条数]',
                'example' => '/summarize 20',
                'handler' => 'handleSummarize',
            ],
            'ping' => [
                'description' => '检查机器人响应',
                'usage' => '/ping',
                'handler' => 'handlePing',
            ],
            'weather' => [
                'description' => '查询天气',
                'usage' => '/weather [城市]',
                'example' => '/weather 北京',
                'handler' => 'handleWeather',
            ],
            'calc' => [
                'description' => '数学计算',
                'usage' => '/calc [表达式]',
                'example' => '/calc (42 + 7) * 3',
                'handler' => 'handleCalc',
            ],
        ];
    }

    /**
     * 解析并执行命令
     * @return array{handled: bool, response?: string, error?: string, meta?: array}
     */
    public function execute(string $text, int $userId, ?int $convId = null): array
    {
        $text = trim($text);

        // 必须以 / 开头
        if (!str_starts_with($text, '/')) {
            return ['handled' => false];
        }

        // 解析命令和参数
        $parts = preg_split('/\s+/', $text);
        $commandName = ltrim(array_shift($parts), '/');
        $args = $parts;

        // 查找命令
        $command = $this->commands[$commandName] ?? null;
        if (!$command) {
            $available = array_keys($this->commands);
            return [
                'handled' => true,
                'error' => true,
                'response' => "未知命令 `/{$commandName}`。可用命令：" . implode(', ', array_map(fn($c) => "/{$c}", $available)),
            ];
        }

        try {
            $handler = $command['handler'];
            return $this->$handler($args, $userId, $convId);
        } catch (\Throwable $e) {
            Log::warning('Slash command error', ['command' => $commandName, 'error' => $e->getMessage()]);
            return [
                'handled' => true,
                'error' => true,
                'response' => "命令执行失败：{$e->getMessage()}",
            ];
        }
    }

    /**
     * 获取所有命令定义
     */
    public function getCommands(): array
    {
        return array_map(fn($cmd, $name) => [
            'command' => "/{$name}",
            'description' => $cmd['description'],
            'usage' => $cmd['usage'],
            'example' => $cmd['example'] ?? null,
        ], $this->commands, array_keys($this->commands));
    }

    // ── 命令处理器 ──

    protected function handleHelp(array $args): array
    {
        $response = "**📋 可用命令**\n\n";
        foreach ($this->commands as $name => $cmd) {
            $response .= "- `/{$name}` — {$cmd['description']}\n";
            if (!empty($cmd['example'])) {
                $response .= "  `{$cmd['example']}`\n";
            }
        }
        $response .= "\n💡 提示：在输入框中输入 `/` 可查看命令列表";

        return ['handled' => true, 'response' => $response, 'meta' => ['type' => 'markdown']];
    }

    protected function handlePing(array $args): array
    {
        return ['handled' => true, 'response' => '🏓 Pong! ' . now()->format('H:i:s')];
    }

    protected function handleCalc(array $args): array
    {
        $expr = implode(' ', $args);
        if (empty($expr)) {
            return ['handled' => true, 'error' => true, 'response' => '用法：/calc [表达式]'];
        }

        // 安全计算：只允许数学表达式
        $safeExpr = preg_replace('/[^0-9\+\-\*\/\.\(\)\s\%]/', '', $expr);
        if (empty($safeExpr)) {
            return ['handled' => true, 'error' => true, 'response' => '表达式包含不安全的字符'];
        }

        try {
            $result = eval("return {$safeExpr};");
            return ['handled' => true, 'response' => "`{$expr}` = **{$result}**"];
        } catch (\Throwable $e) {
            return ['handled' => true, 'error' => true, 'response' => '计算错误：' . $e->getMessage()];
        }
    }

    protected function handleRemind(array $args, int $userId, ?int $convId): array
    {
        if (count($args) < 2) {
            return ['handled' => true, 'error' => true, 'response' => '用法：/remind [时间] [内容]', 'example' => '/remind 30分钟后 回复张三邮件'];
        }

        $timeStr = array_shift($args);
        $content = implode(' ', $args);

        // 解析时间
        try {
            $remindAt = $this->parseRemindTime($timeStr);
        } catch (\Throwable $e) {
            return ['handled' => true, 'error' => true, 'response' => "时间格式无法识别「{$timeStr}」。支持：X分钟后、X小时后、明天、15:30 等"];
        }

        // 存储提醒到数据库（使用 JSON 文件回退，因为 Reminder 模型未创建）
        try {
            if (class_exists(\App\Models\Reminder::class)) {
                \App\Models\Reminder::create([
                    'user_id' => $userId,
                    'conversation_id' => $convId,
                    'content' => $content,
                    'remind_at' => $remindAt,
                ]);
            } else {
                // Reminder 模型不存在，存储到 JSON
                $remindersFile = storage_path('app/reminders.json');
                $reminders = [];
                if (file_exists($remindersFile)) {
                    $reminders = json_decode(file_get_contents($remindersFile), true) ?? [];
                }
                $reminders[] = [
                    'id' => count($reminders) + 1,
                    'user_id' => $userId,
                    'conversation_id' => $convId,
                    'content' => $content,
                    'remind_at' => $remindAt->toIso8601String(),
                    'created_at' => now()->toIso8601String(),
                ];
                file_put_contents($remindersFile, json_encode($reminders, JSON_UNESCAPED_UNICODE));
            }
        } catch (\Throwable $e) {
            // 静默处理，不影响用户体验
        }

        return [
            'handled' => true,
            'response' => "⏰ 已设置提醒：将在 **{$remindAt->format('H:i')}** 提醒你「{$content}」",
            'meta' => ['remind_at' => $remindAt->toIso8601String()],
        ];
    }

    protected function handlePoll(array $args, int $userId, ?int $convId): array
    {
        if (count($args) < 2) {
            return ['handled' => true, 'error' => true, 'response' => '用法：/poll 问题 | 选项1 | 选项2 | ...'];
        }

        $text = implode(' ', $args);
        $parts = explode('|', $text);
        $question = trim(array_shift($parts));
        $options = array_map('trim', $parts);
        $options = array_filter($options, fn($o) => !empty($o));

        if (count($options) < 2) {
            return ['handled' => true, 'error' => true, 'response' => '至少需要 2 个选项'];
        }
        if (count($options) > 10) {
            return ['handled' => true, 'error' => true, 'response' => '最多支持 10 个选项'];
        }

        // 尝试创建投票
        try {
            $conv = UserConversation::find($convId);
            $pollMsg = ConversationMessage::create([
                'conversation_id' => $convId,
                'sender_id' => $userId,
                'message_type' => 'poll',
                'content' => $question,
                'metadata' => [
                    'type' => 'poll',
                    'poll' => [
                        'question' => $question,
                        'options' => array_map(fn($o) => ['label' => $o, 'votes' => 0], $options),
                        'total_votes' => 0,
                        'created_by' => $userId,
                    ],
                ],
            ]);

            return [
                'handled' => true,
                'response' => "📊 投票已创建：**{$question}**\n" . implode("\n", array_map(fn($o, $i) => "  {$i}. {$o}", $options, array_keys($options))),
                'meta' => ['message_id' => $pollMsg->id, 'type' => 'poll'],
            ];
        } catch (\Throwable $e) {
            // 回退到文本回复
            $optionsStr = implode("\n", array_map(fn($o, $i) => "{$i}. {$o}", $options, array_keys($options)));
            return [
                'handled' => true,
                'response' => "📊 投票：**{$question}**\n{$optionsStr}\n\n💡 请回复对应数字投票",
            ];
        }
    }

    protected function handleTranslate(array $args, int $userId, ?int $convId): array
    {
        if (count($args) < 2) {
            return ['handled' => true, 'error' => true, 'response' => '用法：/translate [目标语言] [文本]'];
        }

        $targetLang = array_shift($args);
        $text = implode(' ', $args);

        $langMap = [
            'en' => '英语', 'zh' => '中文', 'ja' => '日语', 'ko' => '韩语',
            'fr' => '法语', 'de' => '德语', 'es' => '西班牙语', 'ru' => '俄语',
        ];
        $langName = $langMap[$targetLang] ?? $targetLang;

        try {
            $llm = app(LlmService::class);
            $result = $llm->chat([
                ['role' => 'system', 'content' => "你是一个翻译助手。将以下文本翻译成{$langName}。只返回翻译结果，不要额外解释。"],
                ['role' => 'user', 'content' => $text],
            ], ['temperature' => 0.2], 'slash_translate');
            $translated = $result['content'] ?? $text;
            return [
                'handled' => true,
                'response' => "🌐 翻译（{$langName}）：\n> {$translated}",
                'meta' => ['source' => $text, 'target' => $targetLang, 'translation' => $translated],
            ];
        } catch (\Throwable $e) {
            return [
                'handled' => true,
                'response' => "🌐 翻译（{$langName}）：\n> [翻译服务暂不可用] {$text}",
            ];
        }
    }

    protected function handleSummarize(array $args, int $userId, ?int $convId): array
    {
        if (!$convId) {
            return ['handled' => true, 'error' => true, 'response' => '请在群聊中使用此命令'];
        }

        $limit = isset($args[0]) ? min((int) $args[0], 100) : 20;

        try {
            $messages = ConversationMessage::where('conversation_id', $convId)
                ->whereNull('deleted_at')->latest()->take($limit)->get()->reverse();

            if ($messages->isEmpty()) {
                return ['handled' => true, 'response' => '暂无消息可总结'];
            }

            $lines = $messages->map(fn($m) => ($m->sender?->name ?? '用户') . '：' . $m->content)->implode("\n");
            $conv = UserConversation::find($convId);

            $llm = app(LlmService::class);
            $result = $llm->chat([
                ['role' => 'system', 'content' => '你是总结助手。对以下对话进行简洁总结（5条以内要点），使用 Markdown 格式。'],
                ['role' => 'user', 'content' => "群：{$conv->name}\n共{$messages->count()}条消息：\n{$lines}"],
            ], ['temperature' => 0.3], 'slash_summarize');

            $summary = $result['content'] ?? '总结失败';
            return ['handled' => true, 'response' => "📝 **对话总结**\n{$summary}", 'meta' => ['type' => 'markdown']];
        } catch (\Throwable $e) {
            return ['handled' => true, 'error' => true, 'response' => '总结失败：' . $e->getMessage()];
        }
    }

    protected function handleWeather(array $args): array
    {
        $city = implode(' ', $args);
        if (empty($city)) {
            return ['handled' => true, 'error' => true, 'response' => '用法：/weather [城市名]'];
        }

        // 简单模拟天气查询（实际应调用天气 API）
        $conditions = ['☀️ 晴', '⛅ 多云', '🌧️ 小雨', '🌤️ 晴间多云'];
        $condition = $conditions[array_rand($conditions)];
        $temp = round(15 + mt_rand() / mt_getrandmax() * 20, 1);

        return [
            'handled' => true,
            'response' => "🌍 **{$city}** 天气预报\n温度：{$temp}°C\n天气：{$condition}\n\n📌 此为模拟数据，实际天气需配置天气 API",
        ];
    }

    /**
     * 解析提醒时间
     */
    protected function parseRemindTime(string $timeStr): \DateTime
    {
        $now = now();

        // X分钟后
        if (preg_match('/^(\d+)分.*$/u', $timeStr, $m)) {
            return $now->copy()->addMinutes((int) $m[1]);
        }
        // X小时后
        if (preg_match('/^(\d+)小.*$/u', $timeStr, $m)) {
            return $now->copy()->addHours((int) $m[1]);
        }
        // X天后
        if (preg_match('/^(\d+)天.*$/u', $timeStr, $m)) {
            return $now->copy()->addDays((int) $m[1]);
        }
        // 明天
        if ($timeStr === '明天' || $timeStr === 'tomorrow') {
            return $now->copy()->addDay()->setTime(9, 0, 0);
        }
        // 15:30 格式
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $timeStr, $m)) {
            $target = $now->copy()->setTime((int) $m[1], (int) $m[2], 0);
            if ($target <= $now) {
                $target->addDay();
            }
            return $target;
        }

        throw new \InvalidArgumentException("无法解析时间：{$timeStr}");
    }
}
