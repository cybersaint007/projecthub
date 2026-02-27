<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Epic: {{ $epic->title }}</h2>
            <a href="{{ route('epics.show', $epic) }}" class="px-3 py-2 border text-sm rounded hover:bg-gray-50">&larr; Back to Epic</a>
        </div>
    </x-slot>

    <div class="max-w-2xl mx-auto bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
        <form method="POST" action="{{ route('epics.update', $epic) }}">
            @csrf
            @method('PUT')
            <div class="mb-4">
                <x-input-label for="title" value="Title" />
                <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title', $epic->title)" required />
                <x-input-error :messages="$errors->get('title')" class="mt-2" />
            </div>
            <div class="mb-4">
                <x-input-label for="description" value="Description" />
                <textarea id="description" name="description" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $epic->description) }}</textarea>
            </div>
            <div class="mb-4">
                <x-input-label for="milestone_tag" value="Milestone Tag" />
                <x-text-input id="milestone_tag" name="milestone_tag" type="text" class="mt-1 block w-full" :value="old('milestone_tag', $epic->milestone_tag)" />
            </div>
            <div class="flex items-center gap-4">
                <x-primary-button>Update Epic</x-primary-button>
                <a href="{{ route('epics.show', $epic) }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>
