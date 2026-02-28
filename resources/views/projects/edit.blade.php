<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('ui.edit_project') }}: {{ $project->name }}</h2>
            <a href="{{ route('projects.show', $project) }}" class="px-3 py-2 border text-sm rounded hover:bg-gray-50">&larr; {{ __('ui.back_to_project') }}</a>
        </div>
    </x-slot>

    <div class="max-w-2xl mx-auto bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
        <form method="POST" action="{{ route('projects.update', $project) }}">
            @csrf
            @method('PUT')
            <div class="mb-4">
                <x-input-label for="name" :value="__('ui.name')" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $project->name)" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>
            <div class="mb-4">
                <x-input-label for="description" :value="__('ui.description')" />
                <textarea id="description" name="description" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $project->description) }}</textarea>
            </div>
            <div class="flex items-center gap-4">
                <x-primary-button>{{ __('ui.update_project') }}</x-primary-button>
                <a href="{{ route('projects.show', $project) }}" class="text-sm text-gray-600 hover:underline">{{ __('ui.cancel') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>
