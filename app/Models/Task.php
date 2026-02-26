<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'epic_id',
        'position',
        'title',
        'description',
        'status',
        'agent',
        'priority',
        'tags',
        'context',
        'instructions',
        'acceptance_criteria',
        'assignee_id',
    ];

    protected $attributes = [
        'status' => 'TODO',
        'priority' => 3,
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'priority' => 'integer',
        ];
    }

    public const STATUSES = ['TODO', 'Backlog', 'Ready', 'InProgress', 'Review', 'Done'];
    public const AGENTS = ['claude_code', 'cursor2', 'deepseek', 'openclaw', 'human'];
    /** @var int Priority 1=low, 3=medium (default), 5=high */
    public const PRIORITY_LOW = 1;
    public const PRIORITY_MEDIUM = 3;
    public const PRIORITY_HIGH = 5;

    public static function priorityOptions(): array
    {
        return [
            self::PRIORITY_LOW => 'Low',
            self::PRIORITY_MEDIUM => 'Medium',
            self::PRIORITY_HIGH => 'High',
        ];
    }

    public function epic(): BelongsTo
    {
        return $this->belongsTo(Epic::class);
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(TaskArtifact::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(TaskReview::class);
    }

    public function taskPrompts(): HasMany
    {
        return $this->hasMany(TaskPrompt::class);
    }

    public function taskLogs(): HasMany
    {
        return $this->hasMany(TaskLog::class)->orderByDesc('created_at');
    }
}
