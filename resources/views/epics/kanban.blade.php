<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('ui.kanban') }}: {{ $epic->title }}</h2>
                <p class="text-sm text-gray-500">
                    <a href="{{ route('projects.show', $epic->project) }}" class="hover:underline">{{ $epic->project->name }}</a>
                    &rarr;
                    <a href="{{ route('epics.show', $epic) }}" class="hover:underline">{{ $epic->title }}</a>
                </p>
            </div>
            @php $epicUserRole = $epic->project->roleFor(auth()->user()); @endphp
            <div class="flex gap-2">
                <a href="{{ route('epics.show', $epic) }}" class="px-3 py-2 border text-sm rounded hover:bg-gray-50">&larr; {{ __('ui.back_to_epic') }}</a>
                <a href="{{ route('backlog.epic.export-v3', $epic) }}" class="px-3 py-2 border text-sm rounded hover:bg-gray-50 text-gray-700">{{ __('ui.export_epic') }}</a>
                @if(($epicUserRole === 'owner' || $epicUserRole === 'editor') || Auth::user()->isAdmin())
                    <a href="{{ route('backlog.epic.import-tasks', $epic) }}" class="px-3 py-2 border text-sm rounded hover:bg-gray-50 text-gray-700">{{ __('ui.import_tasks') }}</a>
                @endif
                <a href="{{ route('tasks.create', $epic) }}" class="px-3 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">{{ __('ui.new_task') }}</a>
            </div>
        </div>
    </x-slot>

    @php
        $statuses = ['Backlog', 'Ready', 'InProgress', 'Review', 'Done'];
        $statusColors = [
            'Backlog' => 'border-gray-300 bg-gray-50',
            'Ready' => 'border-blue-300 bg-blue-50',
            'InProgress' => 'border-yellow-300 bg-yellow-50',
            'Review' => 'border-purple-300 bg-purple-50',
            'Done' => 'border-green-300 bg-green-50',
        ];
        $headerColors = [
            'Backlog' => 'text-gray-700',
            'Ready' => 'text-blue-700',
            'InProgress' => 'text-yellow-700',
            'Review' => 'text-purple-700',
            'Done' => 'text-green-700',
        ];
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        @foreach($statuses as $status)
            <div class="border rounded-lg {{ $statusColors[$status] }} p-3 min-h-[200px]">
                <h3 class="font-semibold text-sm mb-3 {{ $headerColors[$status] }}">
                    {{ $status }}
                    <span class="text-xs font-normal">({{ ($tasks[$status] ?? collect())->count() }})</span>
                </h3>
                <div class="space-y-2">
                    @foreach(($tasks[$status] ?? collect()) as $task)
                        <div class="bg-white rounded p-3 shadow-sm border">
                            <a href="{{ route('tasks.show', $task) }}" class="text-sm font-medium text-gray-800 hover:text-indigo-600">{{ $task->title }}</a>
                            <div class="flex justify-between items-center mt-2">
                                @php
                                    $priorityColors = [1 => 'bg-gray-100 text-gray-600', 3 => 'bg-yellow-100 text-yellow-700', 5 => 'bg-red-100 text-red-700'];
                                    $priorityLabels = \App\Models\Task::priorityOptions();
                                @endphp
                                <span class="text-xs px-1.5 py-0.5 rounded {{ $priorityColors[$task->priority] ?? 'bg-gray-100 text-gray-600' }}">{{ $priorityLabels[$task->priority] ?? 'Medium' }}</span>
                                <span class="text-xs text-gray-400">{{ $task->agent }}</span>
                            </div>
                            {{-- Status change buttons --}}
                            <div class="mt-2 flex gap-1">
                                @php
                                    $idx = array_search($task->status, $statuses);
                                @endphp
                                @if($idx > 0)
                                    <form method="POST" action="{{ route('tasks.status', $task) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="{{ $statuses[$idx - 1] }}">
                                        <button type="submit" class="text-xs px-2 py-1 bg-gray-200 rounded hover:bg-gray-300">&larr;</button>
                                    </form>
                                @endif
                                @if($idx < count($statuses) - 1)
                                    <form method="POST" action="{{ route('tasks.status', $task) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="{{ $statuses[$idx + 1] }}">
                                        <button type="submit" class="text-xs px-2 py-1 bg-gray-200 rounded hover:bg-gray-300">&rarr;</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</x-app-layout>
