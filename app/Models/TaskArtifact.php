<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskArtifact extends Model
{
    protected $fillable = ['task_id', 'type', 'value', 'note'];

    public const TYPES = ['file_path', 'url', 'pr', 'commit', 'note'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
