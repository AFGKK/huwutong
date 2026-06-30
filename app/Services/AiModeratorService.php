<?php

namespace App\Services;

use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\UserConversation;

class AiModeratorService
{
    protected LlmService $llm;

    public function __construct(LlmService $llm)
    {
        $this->llm = $llm;
    }

    /**
     * 生成讨论议程
     */
    public function generateAgenda(int $convId, ?string $topic = null): array
    {
        $conv = UserConversation::findOrFail($convId);
        $recentMessages = ConversationMessage::where('conversation_id', $convId)
            ->whereNull('deleted_at')->latest()->take(20)->get()->reverse();

        $context = '';
        foreach ($recentMessages as $m) {
            $context .= ($m->sender?->name ?? '用户') . ': ' . $m->content . "\n";
        }

        $result = $this->llm->chat([
            ['role' => 'system', 'content' => '你是一个专业的 AI 会议主持人。请根据群聊信息和主题生成讨论议程。
议程格式：
1. 议题标题 — 预估时间
2. 议题标题 — 预估时间
...

要求：
- 议程结构清晰，3-5 个议题
- 每个议题附预估讨论时间
- 基于已有对话推测讨论方向
- 如果指定了主题，围绕该主题展开'],
            ['role' => 'user', 'content' => "群名称：{$conv->name}\n" . ($topic ? "主题：{$topic}\n" : '') . "最近讨论：\n{$context}\n请生成讨论议程。"],
        ], ['temperature' => 0.4], 'moderator_agenda');

        $agenda = $result['content'] ?? '无法生成议程';
        $estimatedTotal = $this->extractTotalMinutes($agenda);

        return [
            'agenda' => $agenda,
            'estimated_minutes' => $estimatedTotal,
            'conversation_name' => $conv->name,
        ];
    }

    /**
     * 争论调解建议
     */
    public function mediateDebate(int $convId): array
    {
        $messages = ConversationMessage::where('conversation_id', $convId)
            ->whereNull('deleted_at')->latest()->take(30)->get()->reverse();

        $lines = '';
        foreach ($messages as $m) {
            $lines .= ($m->sender?->name ?? '用户') . ': ' . $m->content . "\n";
        }

        $result = $this->llm->chat([
            ['role' => 'system', 'content' => '你是一个专业的 AI 调解员。分析以下群聊对话，判断是否存在争论/分歧，并提供调解建议。

请按以下格式回复：
## 争论检测
[是否存在争论: 是/否]
[争论焦点: ...]
[涉及成员: ...]

## 调解建议
[建议的调解方案]

## 解决方案选项
1. ...
2. ...

## 建议下一步
[建议群主/管理员采取的行动]'],
            ['role' => 'user', 'content' => "群聊记录：\n{$lines}\n\n请分析是否存在争论并提供调解建议。"],
        ], ['temperature' => 0.3], 'moderator_mediate');

        $reply = $result['content'] ?? '分析失败';

        $hasDebate = !preg_match('/是否存在争论\s*[:：]\s*否/u', $reply);

        return [
            'analysis' => $reply,
            'has_debate' => $hasDebate,
            'message_count' => count($messages),
        ];
    }

    /**
     * 讨论总结（实时快照）
     */
    public function summarizeDiscussion(int $convId): array
    {
        $messages = ConversationMessage::where('conversation_id', $convId)
            ->whereNull('deleted_at')->latest()->take(50)->get()->reverse();

        if ($messages->isEmpty()) {
            return ['summary' => '暂无消息可供总结', 'key_points' => [], 'message_count' => 0];
        }

        $lines = '';
        foreach ($messages as $m) {
            $lines .= ($m->sender?->name ?? '用户') . ': ' . $m->content . "\n";
        }

        $result = $this->llm->chat([
            ['role' => 'system', 'content' => '你是 AI 会议主持人。对以下群聊讨论进行实时总结，包含：
1. 讨论主题
2. 已达成的共识
3. 未解决的分歧
4. 行动项（谁+做什么）
5. 建议下一步讨论方向

简洁扼要，使用 Markdown 格式。'],
            ['role' => 'user', 'content' => $lines],
        ], ['temperature' => 0.3], 'moderator_summary');

        $summary = $result['content'] ?? '无法总结';

        return [
            'summary' => $summary,
            'message_count' => count($messages),
            'participants' => $messages->pluck('sender.name')->unique()->filter()->values(),
        ];
    }

    /**
     * 检查讨论是否偏离主题
     */
    public function checkTopicFocus(int $convId, string $topic): array
    {
        $messages = ConversationMessage::where('conversation_id', $convId)
            ->whereNull('deleted_at')->latest()->take(20)->get()->reverse();

        $lines = '';
        foreach ($messages as $m) {
            $lines .= ($m->sender?->name ?? '用户') . ': ' . $m->content . "\n";
        }

        $result = $this->llm->chat([
            ['role' => 'system', 'content' => '你是一个 AI 会议主持人。判断以下讨论是否围绕指定主题展开。请回复以下格式：
专注度: 高/中/低
偏离内容: (列出偏离主题的讨论，如无不填)
建议: (如需拉回主题的建议)'],
            ['role' => 'user', 'content' => "主题：{$topic}\n讨论记录：\n{$lines}\n\n请判断是否偏离主题。"],
        ], ['temperature' => 0.3], 'moderator_focus');

        $reply = $result['content'] ?? '分析失败';

        $focusLevel = '中';
        if (preg_match('/专注度\s*[:：]\s*高/u', $reply)) $focusLevel = '高';
        elseif (preg_match('/专注度\s*[:：]\s*低/u', $reply)) $focusLevel = '低';

        return [
            'analysis' => $reply,
            'focus_level' => $focusLevel,
            'message_count' => count($messages),
        ];
    }

    /**
     * 从议程文本中提取总预估时间（分钟）
     */
    protected function extractTotalMinutes(string $agenda): int
    {
        $total = 0;
        if (preg_match_all('/(\d+)\s*分钟/', $agenda, $matches)) {
            foreach ($matches[1] as $min) {
                $total += (int) $min;
            }
        }
        return $total ?: 30; // 默认 30 分钟
    }
}
