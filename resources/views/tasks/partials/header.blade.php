<div class="flex justify-between items-center">
    <div>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $task->title }}</h2>
        <p class="text-sm text-gray-500 mt-0.5">
            <a href="{{ route('projects.show', $task->epic->project) }}" class="hover:underline">{{ $task->epic->project->name }}</a>
            &rarr;
            <a href="{{ route('epics.show', $task->epic) }}" class="hover:underline">{{ $task->epic->title }}</a>
        </p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('epics.show', $task->epic) }}" class="px-3 py-2 border text-sm rounded hover:bg-gray-50">&larr; {{ __('ui.back_to_tasks') }}</a>
        <a href="{{ route('epics.kanban', $task->epic) }}" class="px-3 py-2 border text-sm rounded hover:bg-gray-50">{{ __('ui.kanban') }}</a>
        <a href="{{ route('tasks.edit', $task) }}" class="px-3 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">{{ __('ui.edit') }}</a>
        @if(Auth::user()->isAdmin())
            <form method="POST" action="{{ route('tasks.destroy', $task) }}" class="inline">
                @csrf
                @method('DELETE')
                <button type="submit" onclick="return confirm('{{ __('ui.confirm_delete_task') }}')"
                    class="px-3 py-2 text-sm border border-red-300 text-red-600 rounded hover:bg-red-50">
                    {{ __('ui.delete') }}
                </button>
            </form>
        @endif
    </div>
</div>
