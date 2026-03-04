<div class="bg-white shadow-sm sm:rounded-lg p-5">
    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-4">Task Info</h3>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        {{-- Status --}}
        <div>
            <span class="text-xs text-gray-500 uppercase">{{ __('ui.status') }}</span>
            @php
                $statusColors = [
                    'TODO'       => 'bg-gray-100 text-gray-700',
                    'Backlog'    => 'bg-gray-100 text-gray-700',
                    'Ready'      => 'bg-blue-100 text-blue-700',
                    'InProgress' => 'bg-yellow-100 text-yellow-700',
                    'Review'     => 'bg-purple-100 text-purple-700',
                    'Done'       => 'bg-green-100 text-green-700',
                ];
            @endphp
            <div class="mt-1">
                <form method="POST" action="{{ route('tasks.status', $task) }}" class="inline">
                    @csrf
                    @method('PATCH')
                    <select name="status" onchange="this.form.submit()"
                        class="text-sm border-0 rounded py-1 pr-6 {{ $statusColors[$task->status] ?? 'bg-gray-100 text-gray-700' }} focus:ring-indigo-500">
                        @foreach(\App\Models\Task::STATUSES as $s)
                            <option value="{{ $s }}" {{ $task->status === $s ? 'selected' : '' }}>{{ $s }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>

        {{-- Agent --}}
        <div>
            <span class="text-xs text-gray-500 uppercase">{{ __('ui.agent') }}</span>
            <div class="mt-1 text-sm">{{ $task->agent ?: '—' }}</div>
        </div>

        {{-- Priority --}}
        <div>
            <span class="text-xs text-gray-500 uppercase">{{ __('ui.priority') }}</span>
            @php
                $priorityLabels = \App\Models\Task::priorityOptions();
                $priorityColors = [1 => 'text-gray-600', 3 => 'text-yellow-600', 5 => 'text-red-600'];
            @endphp
            <div class="mt-1 text-sm font-medium {{ $priorityColors[$task->priority] ?? 'text-gray-600' }}">
                {{ $priorityLabels[$task->priority] ?? 'Medium' }}
            </div>
        </div>

        {{-- Tags --}}
        <div>
            <span class="text-xs text-gray-500 uppercase">{{ __('ui.tags') }}</span>
            <div class="mt-1 flex flex-wrap gap-1">
                @forelse($task->tags ?? [] as $tag)
                    <span class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded">{{ $tag }}</span>
                @empty
                    <span class="text-xs text-gray-400">—</span>
                @endforelse
            </div>
        </div>
    </div>
</div>
