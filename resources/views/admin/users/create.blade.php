<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Create User</h2>
            <a href="{{ route('admin.users.index') }}" class="px-3 py-2 border text-sm rounded hover:bg-gray-50">&larr; Back to Users</a>
        </div>
    </x-slot>

    <div class="max-w-md mx-auto bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
        <p class="text-sm text-gray-500 mb-4">A strong password will be generated automatically. You will see it once after creation.</p>

        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            <div class="mb-4">
                <x-input-label for="name" value="Name" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>
            <div class="mb-4">
                <x-input-label for="email" value="Email" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>
            <div class="mb-4">
                <label class="inline-flex items-center">
                    <input type="checkbox" name="is_admin" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" {{ old('is_admin') ? 'checked' : '' }}>
                    <span class="ms-2 text-sm text-gray-600">Admin privileges</span>
                </label>
            </div>
            <x-primary-button>Create User</x-primary-button>
        </form>
    </div>
</x-app-layout>
