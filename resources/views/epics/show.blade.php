<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $epic->title }}</h2>
                <p class="text-sm text-gray-500">
                    <a href="{{ route('projects.show', $epic->project) }}" class="hover:underline">{{ $epic->project->name }}</a>
                    @if($epic->milestone_tag)
                        <span class="ml-2 px-2 py-0.5 bg-purple-100 text-purple-700 text-xs rounded">{{ $epic->milestone_tag }}</span>
                    @endif
                </p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('projects.show', $epic->project) }}" class="px-3 py-2 border text-sm rounded hover:bg-gray-50">&larr; {{ __('ui.back_to_project') }}</a>
                <a href="{{ route('epics.kanban', $epic) }}" class="px-3 py-2 bg-gray-600 text-white text-sm rounded hover:bg-gray-700">{{ __('ui.kanban') }}</a>
                <a href="{{ route('tasks.create', $epic) }}" class="px-3 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">{{ __('ui.new_task') }}</a>
                <a href="{{ route('epics.edit', $epic) }}" class="px-3 py-2 border text-sm rounded hover:bg-gray-50">{{ __('ui.edit') }}</a>
                @if(Auth::user()->isAdmin())
                    <form method="POST" action="{{ route('epics.destroy', $epic) }}" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" onclick="return confirm('{{ __('ui.confirm_delete_epic') }}')"
                            class="px-3 py-2 text-sm border border-red-300 text-red-600 rounded hover:bg-red-50">
                            {{ __('ui.delete') }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    @if($epic->description)
        <div class="bg-white shadow-sm sm:rounded-lg p-4 mb-4">
            <p class="text-gray-600">{{ $epic->description }}</p>
        </div>
    @endif

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg" x-data="{ statusFilter: '' }">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium">{{ __('ui.tasks') }} ({{ $epic->tasks->count() }})</h3>
                @if($epic->tasks->isNotEmpty())
                    <select x-model="statusFilter" class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">{{ __('ui.all_statuses') }}</option>
                        @foreach(\App\Models\Task::STATUSES as $s)
                            <option value="{{ $s }}">{{ $s }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
            @if($epic->tasks->isEmpty())
                <p class="text-gray-500">{{ __('ui.no_tasks_yet') }}</p>
            @else
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.title') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.status') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.agent') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.priority') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($epic->tasks as $task)
                            <tr x-show="statusFilter === '' || statusFilter === '{{ $task->status }}'">
                                <td class="px-4 py-3">
                                    <a href="{{ route('tasks.show', $task) }}" class="text-indigo-600 hover:underline">{{ $task->title }}</a>
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $statusColors = [
                                            'Backlog' => 'bg-gray-100 text-gray-700',
                                            'Ready' => 'bg-blue-100 text-blue-700',
                                            'InProgress' => 'bg-yellow-100 text-yellow-700',
                                            'Review' => 'bg-purple-100 text-purple-700',
                                            'Done' => 'bg-green-100 text-green-700',
                                        ];
                                    @endphp
                                    <span class="px-2 py-1 text-xs rounded {{ $statusColors[$task->status] ?? '' }}">{{ $task->status }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $task->agent }}</td>
                                <td class="px-4 py-3">
                                    @php
                                        $priorityColors = [1 => 'text-gray-500', 3 => 'text-yellow-600', 5 => 'text-red-600'];
                                        $priorityLabels = \App\Models\Task::priorityOptions();
                                    @endphp
                                    <span class="text-sm {{ $priorityColors[$task->priority] ?? 'text-gray-500' }}">{{ $priorityLabels[$task->priority] ?? 'Medium' }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-app-layout>
