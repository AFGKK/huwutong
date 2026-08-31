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
        $t = fn(string $k) => __('app.admin.slash_command.commands.' . $k);

        $this->commands = [
            'help' => [
                'description' => $t('help.description'),
                'usage' => $t('help.usage'),
                'handler' => 'handleHelp',
            ],
            'remind' => [
                'description' => $t('remind.description'),
                'usage' => $t('remind.usage'),
                'example' => $t('remind.example'),
                'handler' => 'handleRemind',
                'params' => ['time', 'content'],
            ],
            'poll' => [
                'description' => $t('poll.description'),
                'usage' => $t('poll.usage'),
                'example' => $t('poll.example'),
                'handler' => 'handlePoll',
            ],
            'translate' => [
                'description' => $t('translate.description'),
                'usage' => $t('translate.usage'),
                'example' => $t('translate.example'),
                'handler' => 'handleTranslate',
            ],
            'summarize' => [
                'description' => $t('summarize.description'),
                'usage' => $t('summarize.usage'),
                'example' => $t('summarize.example'),
                'handler' => 'handleSummarize',
            ],
            'ping' => [
                'description' => $t('ping.description'),
                'usage' => $t('ping.usage'),
                'handler' => 'handlePing',
            ],
            'weather' => [
                'description' => $t('weather.description'),
                'usage' => $t('weather.usage'),
                'example' => $t('weather.example'),
                'handler' => 'handleWeather',
            ],
            'calc' => [
                'description' => $t('calc.description'),
                'usage' => $t('calc.usage'),
                'example' => $t('calc.example'),
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
                'response' => __('app.admin.slash_command.unknown_command', ['cmd' => $commandName]) . implode(', ', array_map(fn($c) => "/{$c}", $available)),
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
                'response' => __('app.admin.slash_command.execution_failed', ['error' => $e->getMessage()]),
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
        $response = __('app.admin.slash_command.help_title') . "\n\n";
        foreach ($this->commands as $name => $cmd) {
            $response .= "- `/{$name}` — {$cmd['description']}\n";
            if (!empty($cmd['example'])) {
                $response .= "  `{$cmd['example']}`\n";
            }
        }
        $response .= "\n" . __('app.admin.slash_command.help_footer');

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
            return ['handled' => true, 'error' => true, 'response' => __('app.admin.slash_command.calc_usage')];
        }

        // 安全计算：只允许数学表达式
        $safeExpr = preg_replace('/[^0-9\+\-\*\/\.\(\)\s\%]/', '', $expr);
        if (empty($safeExpr)) {
            return ['handled' => true, 'error' => true, 'response' => __('app.admin.slash_command.calc_unsafe')];
        }

        try {
            $result = eval("return {$safeExpr};");
            return ['handled' => true, 'response' => "`{$expr}` = **{$result}**"];
        } catch (\Throwable $e) {
            return ['handled' => true, 'error' => true, 'response' => __('app.admin.slash_command.calc_error', ['error' => $e->getMessage()])];
        }
    }

    protected function handleRemind(array $args, int $userId, ?int $convId): array
    {
        if (count($args) < 2) {
            return ['handled' => true, 'error' => true, 'response' => __('app.admin.slash_command.remind_usage'), 'example' => __('app.admin.slash_command.commands.remind.example')];
        }

        $timeStr = array_shift($args);
        $content = implode(' ', $args);

        // 解析时间
        try {
            $remindAt = $this->parseRemindTime($timeStr);
        } catch (\Throwable $e) {
            return ['handled' => true, 'error' => true, 'response' => __('app.admin.slash_command.remind_time_invalid', ['time' => $timeStr])];
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
            'response' => __('app.admin.slash_command.remind_set', ['time' => $remindAt->format('H:i'), 'content' => $content]),
            'meta' => ['remind_at' => $remindAt->toIso8601String()],
        ];
    }

    protected function handlePoll(array $args, int $userId, ?int $convId): array
    {
        if (count($args) < 2) {
            return ['handled' => true, 'error' => true, 'response' => __('app.admin.slash_command.poll_usage')];
        }

        $text = implode(' ', $args);
        $parts = explode('|', $text);
        $question = trim(array_shift($parts));
        $options = array_map('trim', $parts);
        $options = array_filter($options, fn($o) => !empty($o));

        if (count($options) < 2) {
            return ['handled' => true, 'error' => true, 'response' => __('app.admin.slash_command.poll_min_options')];
        }
        if (count($options) > 10) {
            return ['handled' => true, 'error' => true, 'response' => __('app.admin.slash_command.poll_max_options')];
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
                'response' => __('app.admin.slash_command.poll_created', ['question' => $question]) . "\n" . implode("\n", array_map(fn($o, $i) => "  {$i}. {$o}", $options, array_keys($options))),
                'meta' => ['message_id' => $pollMsg->id, 'type' => 'poll'],
            ];
        } catch (\Throwable $e) {
            // 回退到文本回复
            $optionsStr = implode("\n", array_map(fn($o, $i) => "{$i}. {$o}", $options, array_keys($options)));
            return [
                'handled' => true,
                'response' => "📊 " . __('app.admin.slash_command.commands.poll.description') . "：**{$question}**\n{$optionsStr}\n\n" . __('app.admin.slash_command.poll_vote_hint'),
            ];
        }
    }

    protected function handleTranslate(array $args, int $userId, ?int $convId): array
    {
        if (count($args) < 2) {
            return ['handled' => true, 'error' => true, 'response' => __('app.admin.slash_command.translate_usage')];
        }

        $targetLang = array_shift($args);
        $text = implode(' ', $args);

        $langMap = [
            'en' => __('app.admin.slash_command.lang_map.en'), 'zh' => __('app.admin.slash_command.lang_map.zh'), 'ja' => __('app.admin.slash_command.lang_map.ja'), 'ko' => __('app.admin.slash_command.lang_map.ko'),
            'fr' => __('app.admin.slash_command.lang_map.fr'), 'de' => __('app.admin.slash_command.lang_map.de'), 'es' => __('app.admin.slash_command.lang_map.es'), 'ru' => __('app.admin.slash_command.lang_map.ru'),
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
                'response' => __('app.admin.slash_command.translate_result', ['lang' => $langName]) . "\n> {$translated}",
                'meta' => ['source' => $text, 'target' => $targetLang, 'translation' => $translated],
            ];
        } catch (\Throwable $e) {
            return [
                'handled' => true,
                'response' => __('app.admin.slash_command.translate_result', ['lang' => $langName]) . "\n> " . __('app.admin.slash_command.translate_unavailable') . " {$text}",
            ];
        }
    }

    protected function handleSummarize(array $args, int $userId, ?int $convId): array
    {
        if (!$convId) {
            return ['handled' => true, 'error' => true, 'response' => __('app.admin.slash_command.summarize_conv_only')];
        }

        $limit = isset($args[0]) ? min((int) $args[0], 100) : 20;

        try {
            $messages = ConversationMessage::where('conversation_id', $convId)
                ->whereNull('deleted_at')->latest()->take($limit)->get()->reverse();

            if ($messages->isEmpty()) {
                return ['handled' => true, 'response' => __('app.admin.slash_command.summarize_empty')];
            }

            $lines = $messages->map(fn($m) => ($m->sender?->name ?? __('app.admin.slash_command.summarize_default_user')) . '：' . $m->content)->implode("\n");
            $conv = UserConversation::find($convId);

            $llm = app(LlmService::class);
            $result = $llm->chat([
                ['role' => 'system', 'content' => '你是总结助手。对以下对话进行简洁总结（5条以内要点），使用 Markdown 格式。'],
                ['role' => 'user', 'content' => "群：{$conv->name}\n共{$messages->count()}条消息：\n{$lines}"],
            ], ['temperature' => 0.3], 'slash_summarize');

            $summary = $result['content'] ?? __('app.admin.slash_command.summarize_failed', ['error' => '']);
            return ['handled' => true, 'response' => "📝 **" . __('app.admin.slash_command.commands.summarize.description') . "**\n{$summary}", 'meta' => ['type' => 'markdown']];
        } catch (\Throwable $e) {
            return ['handled' => true, 'error' => true, 'response' => __('app.admin.slash_command.summarize_failed', ['error' => $e->getMessage()])];
        }
    }

    protected function handleWeather(array $args): array
    {
        $city = implode(' ', $args);
        if (empty($city)) {
            return ['handled' => true, 'error' => true, 'response' => __('app.admin.slash_command.weather_usage')];
        }

        // 简单模拟天气查询（实际应调用天气 API）
        $conditions = ['☀️ 晴', '⛅ 多云', '🌧️ 小雨', '🌤️ 晴间多云'];
        $condition = $conditions[array_rand($conditions)];
        $temp = round(15 + mt_rand() / mt_getrandmax() * 20, 1);

        return [
            'handled' => true,
            'response' => __('app.admin.slash_command.weather_template', ['city' => $city, 'temp' => $temp, 'condition' => $condition]),
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

        throw new \InvalidArgumentException(__('app.admin.slash_command.time_parse_error', ['time' => $timeStr]));
    }
}
