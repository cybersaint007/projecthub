<x-guest-layout>
    <div class="mb-4 text-center">
        <h1 class="text-xl font-bold text-gray-800">ProjectHub</h1>
        <p class="text-sm text-gray-500">{{ __('ui.reset_password') }}</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.update') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <x-input-label for="email" :value="__('ui.email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', $email)" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('ui.new_password')" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('ui.confirm_password')" />
            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button class="w-full justify-center">
                {{ __('ui.reset_password') }}
            </x-primary-button>
        </div>
    </form>

    <div class="mt-4 text-center">
        <a href="{{ route('login') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('ui.back_to_login') }}</a>
    </div>
</x-guest-layout>
