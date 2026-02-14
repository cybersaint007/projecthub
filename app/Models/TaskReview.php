<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskReview extends Model
{
    protected $fillable = ['task_id', 'result', 'note'];

    public const RESULTS = ['pass', 'changes_requested'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
