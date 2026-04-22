<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('ui.agent_dispatch_dashboard') }}</h2>
            <a href="{{ request()->url() }}" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">{{ __('ui.refresh') }}</a>
        </div>
    </x-slot>

    <meta http-equiv="refresh" content="30">

    <div class="space-y-6">

        {{-- Section 1: Active Leases --}}
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center gap-3">
                <h3 class="font-semibold text-gray-800">{{ __('ui.active_leases') }}</h3>
                <span class="px-2 py-0.5 text-xs font-medium bg-green-100 text-green-800 rounded-full">{{ $activeLeases->count() }}</span>
            </div>

            @if($activeLeases->isEmpty())
                <p class="px-6 py-4 text-sm text-gray-500">{{ __('ui.no_active_leases') }}</p>
            @else
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.task') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.project') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.epic') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.worker_id') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.agent_type') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.leased_at') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.expires_in') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($activeLeases as $task)
                            @php
                                $project = $task->epic?->project;
                                $expiresInMinutes = (int) now()->diffInMinutes($task->leased_until, false);
                            @endphp
                            <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location='{{ route('tasks.show', $task) }}'">
                                <td class="px-6 py-4 text-sm font-medium text-indigo-600">{{ $task->title }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $project?->name ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $task->epic?->title ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500 font-mono">{{ $task->leased_by }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $task->agent ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $task->claimed_at?->format('H:i:s') ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm {{ $expiresInMinutes <= 5 ? 'text-red-600 font-semibold' : 'text-gray-700' }}">
                                    {{ $expiresInMinutes }} {{ __('ui.min') }}
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="px-2 py-0.5 rounded text-xs font-medium
                                        @if($task->status === 'Done') bg-green-100 text-green-800
                                        @elseif($task->status === 'InProgress') bg-blue-100 text-blue-800
                                        @elseif($task->status === 'Review') bg-yellow-100 text-yellow-800
                                        @elseif($task->status === 'Blocked') bg-red-100 text-red-800
                                        @else bg-gray-100 text-gray-800
                                        @endif">
                                        {{ $task->status }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Section 2: Recent Completions --}}
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center gap-3">
                <h3 class="font-semibold text-gray-800">{{ __('ui.recent_completions') }} <span class="text-sm font-normal text-gray-500">({{ __('ui.last_24_hours') }})</span></h3>
                <span class="px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">{{ $recentCompletions->count() }}</span>
            </div>

            @if($recentCompletions->isEmpty())
                <p class="px-6 py-4 text-sm text-gray-500">{{ __('ui.no_completions_24h') }}</p>
            @else
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.task') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.project') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.worker_id') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.completed_at') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.final_status') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.log_count') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($recentCompletions as $task)
                            @php $project = $task->epic?->project; @endphp
                            <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location='{{ route('tasks.show', $task) }}'">
                                <td class="px-6 py-4 text-sm font-medium text-indigo-600">{{ $task->title }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $project?->name ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500 font-mono">{{ $task->leased_by ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $task->claimed_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="px-2 py-0.5 rounded text-xs font-medium
                                        @if($task->status === 'Done') bg-green-100 text-green-800
                                        @elseif($task->status === 'Review') bg-yellow-100 text-yellow-800
                                        @else bg-gray-100 text-gray-800
                                        @endif">
                                        {{ $task->status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $task->log_count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Section 3: Recovered Leases --}}
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center gap-3">
                <h3 class="font-semibold text-gray-800">{{ __('ui.recovered_leases') }} <span class="text-sm font-normal text-gray-500">({{ __('ui.failures_last_24h') }})</span></h3>
                <span class="px-2 py-0.5 text-xs font-medium bg-red-100 text-red-800 rounded-full">{{ $recoveredLeases->count() }}</span>
            </div>

            @if($recoveredLeases->isEmpty())
                <p class="px-6 py-4 text-sm text-gray-500">{{ __('ui.no_recovered_leases_24h') }}</p>
            @else
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.task') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.project') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.worker_id') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.expired_at') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.recovery_message') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($recoveredLeases as $log)
                            @php $task = $log->task; $project = $task?->epic?->project; @endphp
                            <tr class="bg-red-50 hover:bg-red-100 cursor-pointer" onclick="window.location='{{ $task ? route('tasks.show', $task) : '#' }}'">
                                <td class="px-6 py-4 text-sm font-medium text-indigo-600">{{ $task?->title ?? __('ui.task_deleted') }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $project?->name ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500 font-mono">{{ $task?->leased_by ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                                <td class="px-6 py-4 text-sm text-red-700">{{ $log->content }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

    </div>
</x-app-layout>
