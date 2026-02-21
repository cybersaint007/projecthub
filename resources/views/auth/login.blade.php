<x-guest-layout>
    <div class="mb-4 text-center">
        <h1 class="text-xl font-bold text-gray-800">ProjectHub</h1>
        <p class="text-sm text-gray-500">Sign in to your account</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
            <div class="mt-2 text-right">
                <a href="{{ route('password.request') }}" class="text-sm text-gray-600 hover:text-gray-900">忘記密碼？</a>
            </div>
        </div>
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">Remember me</span>
            </label>
        </div>
        <div class="flex items-center justify-end mt-4">
            <x-primary-button class="w-full justify-center">Log in</x-primary-button>
        </div>
    </form>
</x-guest-layout>
