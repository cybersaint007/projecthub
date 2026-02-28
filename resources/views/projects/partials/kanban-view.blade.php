@php
    $canUpdate = ($userRole === 'owner' || $userRole === 'editor') || Auth::user()->isAdmin();
    $statusColors = [
        'Backlog' => 'bg-gray-100 text-gray-600',
        'Ready' => 'bg-blue-100 text-blue-700',
        'InProgress' => 'bg-yellow-100 text-yellow-700',
        'Review' => 'bg-purple-100 text-purple-700',
        'Done' => 'bg-green-100 text-green-700',
    ];
@endphp
@if($project->epics->isEmpty())
    <p class="text-gray-500">{{ __('ui.no_epics_kanban') }}</p>
@else
    <div class="overflow-x-auto pb-4">
        <div id="kanban-columns" class="flex gap-4 min-w-max">
            @foreach($project->epics as $epic)
                @if($epic->trashed())
                    <div class="kanban-column flex-shrink-0 w-80 rounded-lg border bg-gray-50 opacity-60 p-3" data-epic-id="{{ $epic->id }}">
                        <div class="kanban-header flex items-center gap-2 mb-3">
                            <span class="font-semibold text-gray-500 line-through">{{ $epic->title }}</span>
                            <span class="text-xs bg-red-100 text-red-700 px-1.5 rounded">{{ __('ui.deleted') }}</span>
                        </div>
                        <div class="task-sortable min-h-[120px] space-y-2" data-epic-id="{{ $epic->id }}">
                            @foreach($epic->tasks as $task)
                                <div class="kanban-card task-row bg-white rounded p-3 shadow-sm border border-gray-200" data-task-id="{{ $task->id }}">
                                    <span class="text-sm text-gray-400">{{ $task->title }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="kanban-column flex-shrink-0 w-80 rounded-lg border border-gray-200 bg-gray-50/80 shadow-sm p-3" data-epic-id="{{ $epic->id }}">
                        <div class="kanban-header flex items-center gap-2 mb-3">
                            @if($canUpdate)
                                <span class="kanban-column-handle cursor-grab active:cursor-grabbing text-gray-400 hover:text-gray-600 shrink-0 inline-flex items-center justify-center min-w-[28px] select-none touch-none" style="user-select:none;-webkit-user-select:none" title="Drag to reorder columns" role="button" tabindex="-1">&#8942;&#8942;</span>
                            @endif
                            <a href="{{ route('epics.show', $epic) }}" class="font-semibold text-gray-800 hover:text-indigo-600 truncate flex-1 min-w-0">{{ $epic->title }}</a>
                            <span class="text-xs text-gray-500 shrink-0">{{ $epic->tasks->count() }}</span>
                        </div>
                        <div class="task-sortable min-h-[120px] space-y-2" data-epic-id="{{ $epic->id }}">
                            @foreach($epic->tasks as $task)
                                @if($task->trashed())
                                    <div class="kanban-card task-row bg-white rounded p-3 shadow-sm border border-gray-200 opacity-60" data-task-id="{{ $task->id }}">
                                        <span class="text-sm text-gray-400 line-through">{{ $task->title }}</span>
                                    </div>
                                @else
                                    <div class="kanban-card task-row bg-white rounded p-3 shadow-sm border border-gray-200 hover:border-gray-300 group" data-task-id="{{ $task->id }}">
                                        <div class="flex items-start gap-2">
                                            @if($canUpdate)
                                                <span class="kanban-card-handle cursor-grab active:cursor-grabbing text-gray-400 hover:text-gray-600 shrink-0 inline-flex items-center justify-center min-w-[24px] select-none touch-none" style="user-select:none;-webkit-user-select:none" title="Drag to reorder or move to another column" role="button" tabindex="-1">&#8942;&#8942;</span>
                                            @endif
                                            <a href="{{ route('tasks.show', $task) }}" class="text-sm font-medium text-gray-800 hover:text-indigo-600 flex-1 min-w-0 break-words">{{ $task->title }}</a>
                                        </div>
                                        <div class="flex justify-between items-center mt-2">
                                            <span class="text-xs px-1.5 py-0.5 rounded {{ $statusColors[$task->status] ?? '' }}">{{ $task->status }}</span>
                                            <span class="text-xs text-gray-400">{{ \App\Models\Task::priorityOptions()[$task->priority] ?? 'Medium' }}</span>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
@endif
