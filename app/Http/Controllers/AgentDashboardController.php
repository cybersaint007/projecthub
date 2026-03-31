<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskLog;
use Illuminate\Http\Request;

class AgentDashboardController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $activeLeases = Task::with(['epic.project'])
            ->whereNotNull('leased_by')
            ->where('leased_until', '>', now())
            ->orderBy('leased_until')
            ->get();

        $recentCompletions = Task::with(['epic.project'])
            ->whereNotNull('claimed_at')
            ->whereIn('status', ['Done', 'Review'])
            ->where('claimed_at', '>', now()->subHours(24))
            ->withCount(['taskLogs as log_count' => function ($q) {
                $q->whereIn('log_type', ['ai', 'system']);
            }])
            ->orderByDesc('claimed_at')
            ->limit(50)
            ->get();

        $recoveredLeases = TaskLog::with(['task.epic.project'])
            ->where('log_type', 'system')
            ->where('content', 'like', 'Lease expired%')
            ->where('created_at', '>', now()->subHours(24))
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('agent.dashboard', compact('activeLeases', 'recentCompletions', 'recoveredLeases'));
    }
}
