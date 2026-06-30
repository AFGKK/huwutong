<?php

namespace App\Enums;

enum AiMemorySource: string
{
    case AiExtracted = 'ai_extracted';   // AI从对话中自动提取
    case Manual = 'manual';              // 用户手动添加
    case System = 'system';              // 系统自动记录
    case Conversation = 'conversation';  // 从对话历史中归纳

    public function label(): string
    {
        return match ($this) {
            self::AiExtracted => 'AI提取',
            self::Manual => '手动添加',
            self::System => '系统记录',
            self::Conversation => '对话归纳',
        };
    }
}
