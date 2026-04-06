<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskReview extends Model
{
    protected $fillable = ['task_id', 'result', 'note', 'route_exists', 'ui_exists', 'service_exists', 'test_exists'];

    protected $casts = [
        'route_exists'   => 'boolean',
        'ui_exists'      => 'boolean',
        'service_exists' => 'boolean',
        'test_exists'    => 'boolean',
    ];

    public const RESULTS = ['pass', 'changes_requested'];

    public function truthAuditPassed(): bool
    {
        return $this->route_exists && $this->ui_exists && $this->service_exists && $this->test_exists;
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
