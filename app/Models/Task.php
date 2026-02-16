<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $fillable = [
        'epic_id',
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

    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }

    public const STATUSES = ['Backlog', 'Ready', 'InProgress', 'Review', 'Done'];
    public const AGENTS = ['claude_code', 'cursor2', 'human'];
    public const PRIORITIES = ['low', 'medium', 'high'];

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
}
