<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskPrompt extends Model
{
    public const AGENT_TYPES = ['claude_code', 'cursor2', 'human'];

    public const FORMAT_TYPES = ['structured', 'freeform', 'raw'];

    protected $table = 'task_prompts';

    protected $fillable = [
        'task_id',
        'agent_type',
        'format_type',
        'title',
        'version',
        'content',
        'created_by',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public static function nextVersionFor(int $taskId, string $agentType): int
    {
        $max = static::where('task_id', $taskId)
            ->where('agent_type', $agentType)
            ->max('version');

        return (int) $max + 1;
    }
}
