{{-- Description: view-first, Alpine edit toggle. editing initialised to true on validation error. --}}
<div class="bg-white shadow-sm sm:rounded-lg p-5"
     x-data="{ editing: {{ $errors->has('description') ? 'true' : 'false' }} }">

    <div class="flex items-center justify-between mb-3">
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('ui.description') }}</h3>
        <button type="button" x-show="!editing" @click="editing = true"
                class="text-xs text-indigo-600 hover:underline">{{ __('ui.edit') }}</button>
    </div>

    {{-- Read-only view --}}
    <div x-show="!editing">
        @if($task->description)
            <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $task->description }}</p>
        @else
            <p class="text-sm text-gray-400 italic">No description provided.</p>
        @endif
    </div>

    {{-- Edit form --}}
    <div x-show="editing" x-cloak>
        <form method="POST" action="{{ route('tasks.description.update', $task) }}">
            @csrf
            @method('PATCH')
            <textarea name="description" rows="5"
                class="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                placeholder="{{ __('ui.task_description_placeholder') }}">{{ old('description', $task->description) }}</textarea>
            <x-input-error :messages="$errors->get('description')" class="mt-2" />
            <div class="mt-2 flex gap-2">
                <button type="submit"
                    class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700">{{ __('ui.update_description') }}</button>
                <button type="button" @click="editing = false"
                    class="px-3 py-1.5 text-sm border rounded hover:bg-gray-50">{{ __('ui.cancel') }}</button>
            </div>
        </form>
    </div>
</div>
