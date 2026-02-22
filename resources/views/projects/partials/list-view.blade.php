@php
    $canUpdate = ($userRole === 'owner' || $userRole === 'editor') || Auth::user()->isAdmin();
@endphp
<div class="space-y-6">
    @if($project->epics->isEmpty())
        <p class="text-gray-500">No epics yet.</p>
    @else
        <div id="epic-sortable" class="space-y-3">
            @foreach($project->epics as $epic)
                @if($epic->trashed())
                    <div class="border rounded-lg p-4 bg-gray-50 opacity-60 epic-row" data-epic-id="{{ $epic->id }}">
                        <div class="flex justify-between items-start">
                            <div>
                                <span class="font-medium text-gray-400 line-through">{{ $epic->title }}</span>
                                <span class="ml-2 px-2 py-0.5 bg-red-100 text-red-700 text-xs rounded">Deleted</span>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="border rounded-lg p-4 hover:bg-gray-50 epic-row" data-epic-id="{{ $epic->id }}">
                        <div class="flex justify-between items-start gap-2">
                            @if($canUpdate)
                                <span class="epic-handle cursor-grab active:cursor-grabbing text-gray-400 hover:text-gray-600 shrink-0" title="Drag to reorder">
                                    <i class="bi bi-grip-vertical text-lg" aria-hidden="true"></i>
                                </span>
                            @endif
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('epics.show', $epic) }}" class="font-medium text-indigo-600 hover:underline">{{ $epic->title }}</a>
                                @if($epic->milestone_tag)
                                    <span class="ml-2 px-2 py-0.5 bg-purple-100 text-purple-700 text-xs rounded">{{ $epic->milestone_tag }}</span>
                                @endif
                                @if($epic->description)
                                    <p class="text-sm text-gray-500 mt-1">{{ Str::limit($epic->description, 100) }}</p>
                                @endif
                            </div>
                            <div class="flex gap-3 items-center shrink-0">
                                <a href="{{ route('epics.kanban', $epic) }}" class="text-sm text-indigo-600 hover:underline">Kanban</a>
                                <span class="text-sm text-gray-400">{{ $epic->tasks->count() }} tasks</span>
                            </div>
                        </div>
                        {{-- Tasks for this epic (cross-epic drag supported) --}}
                        @if($epic->tasks->isNotEmpty())
                            <div class="mt-3 ml-6 task-sortable border-l-2 border-gray-200 pl-4" data-epic-id="{{ $epic->id }}">
                                @foreach($epic->tasks as $task)
                                    @if($task->trashed())
                                        <div class="task-row flex items-center gap-2 py-2 text-gray-400 line-through" data-task-id="{{ $task->id }}">
                                            <span class="text-sm">{{ $task->title }}</span>
                                            <span class="text-xs bg-red-100 text-red-700 px-1.5 rounded">Deleted</span>
                                        </div>
                                    @else
                                        <div class="task-row flex items-center gap-2 py-2 hover:bg-gray-50 rounded group" data-task-id="{{ $task->id }}">
                                            @if($canUpdate)
                                                <span class="task-handle cursor-grab active:cursor-grabbing text-gray-400 hover:text-gray-600 shrink-0 opacity-0 group-hover:opacity-100" title="Drag to reorder">
                                                    <i class="bi bi-grip-vertical" aria-hidden="true"></i>
                                                </span>
                                            @endif
                                            <a href="{{ route('tasks.show', $task) }}" class="text-sm text-indigo-600 hover:underline flex-1 min-w-0 truncate">{{ $task->title }}</a>
                                            @php
                                                $statusColors = [
                                                    'Backlog' => 'bg-gray-100 text-gray-700',
                                                    'Ready' => 'bg-blue-100 text-blue-700',
                                                    'InProgress' => 'bg-yellow-100 text-yellow-700',
                                                    'Review' => 'bg-purple-100 text-purple-700',
                                                    'Done' => 'bg-green-100 text-green-700',
                                                ];
                                            @endphp
                                            <span class="px-2 py-0.5 text-xs rounded shrink-0 {{ $statusColors[$task->status] ?? '' }}">{{ $task->status }}</span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            @endforeach
        </div>
    @endif
</div>
