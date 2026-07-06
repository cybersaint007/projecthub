<?php

namespace App\Support;

use Illuminate\Support\Str;

class AgentType
{
    private const LABELS = [
        'claude_code' => 'Claude Code',
        'codex' => 'Codex',
        'cursor2' => 'Cursor 2',
        'cursor_2' => 'Cursor 2',
        'deepseek' => 'DeepSeek',
        'openclaw' => 'OpenClaw',
        'ollama' => 'Ollama',
        'hermes' => 'Hermes',
        'human' => 'Human',
        'custom' => 'Custom',
    ];

    public static function label(?string $agentType): string
    {
        if ($agentType === null || $agentType === '') {
            return '';
        }

        return self::LABELS[$agentType]
            ?? (string) Str::of($agentType)->replace(['_', '-'], ' ')->title();
    }
}