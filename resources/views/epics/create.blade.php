<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">New Epic in {{ $project->name }}</h2>
            <a href="{{ route('projects.show', $project) }}" class="px-3 py-2 border text-sm rounded hover:bg-gray-50">&larr; Back to Project</a>
        </div>
    </x-slot>

    <div class="max-w-2xl mx-auto bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
        <form method="POST" action="{{ route('epics.store', $project) }}">
            @csrf
            <div class="mb-4">
                <x-input-label for="title" value="Title" />
                <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title')" required />
                <x-input-error :messages="$errors->get('title')" class="mt-2" />
            </div>
            <div class="mb-4">
                <x-input-label for="description" value="Description" />
                <textarea id="description" name="description" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description') }}</textarea>
            </div>
            <div class="mb-4">
                <x-input-label for="milestone_tag" value="Milestone Tag (optional)" />
                <x-text-input id="milestone_tag" name="milestone_tag" type="text" class="mt-1 block w-full" :value="old('milestone_tag')" />
            </div>
            <x-primary-button>Create Epic</x-primary-button>
        </form>
    </div>
</x-app-layout>
