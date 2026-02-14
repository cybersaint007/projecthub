<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Change Password</h2>
    </x-slot>

    <div class="max-w-md mx-auto bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
        @if(Auth::user()->force_password_reset)
            <div class="mb-4 p-3 bg-yellow-100 border border-yellow-300 text-yellow-800 rounded text-sm">
                You must change your password before continuing.
            </div>
        @endif

        <form method="POST" action="{{ route('password.change.update') }}">
            @csrf
            <div class="mb-4">
                <x-input-label for="current_password" value="Current Password" />
                <x-text-input id="current_password" name="current_password" type="password" class="mt-1 block w-full" required />
                <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
            </div>
            <div class="mb-4">
                <x-input-label for="password" value="New Password" />
                <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>
            <div class="mb-4">
                <x-input-label for="password_confirmation" value="Confirm New Password" />
                <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required />
            </div>
            <x-primary-button>Change Password</x-primary-button>
        </form>
    </div>
</x-app-layout>
