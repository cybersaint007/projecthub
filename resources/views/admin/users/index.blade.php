<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('ui.manage_users') }}</h2>
            <a href="{{ route('admin.users.create') }}" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">{{ __('ui.new_user') }}</a>
        </div>
    </x-slot>

    {{-- Show generated password (one-time display) --}}
    @if(session('new_user_password'))
        <div class="mb-4 p-4 bg-yellow-50 border-2 border-yellow-400 rounded-lg">
            <h3 class="font-bold text-yellow-800 mb-2">{{ __('ui.initial_password_title') }}</h3>
            <p class="text-sm text-yellow-700 mb-2">
                {{ __('ui.user') }}: <strong>{{ session('new_user_name') }}</strong> ({{ session('new_user_email') }})
            </p>
            <div class="flex items-center gap-2">
                <code id="gen-password" class="px-3 py-2 bg-white border rounded font-mono text-lg select-all">{{ session('new_user_password') }}</code>
                <button onclick="navigator.clipboard.writeText(document.getElementById('gen-password').textContent).then(() => this.textContent = '{{ __('ui.copied') }}'); setTimeout(() => this.textContent = '{{ __('ui.copy') }}', 2000)" class="px-3 py-2 bg-yellow-600 text-white text-sm rounded hover:bg-yellow-700">{{ __('ui.copy') }}</button>
            </div>
            <p class="text-xs text-yellow-600 mt-2">{{ __('ui.initial_password_warning') }}</p>
        </div>
    @endif

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.name') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.email') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.role') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ui.password_reset') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach($users as $user)
                    <tr>
                        <td class="px-6 py-4 font-medium">
                            <a href="{{ route('admin.users.show', $user) }}" class="text-indigo-600 hover:underline">{{ $user->name }}</a>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $user->email }}</td>
                        <td class="px-6 py-4 text-sm">
                            @if($user->is_admin)
                                <span class="px-2 py-0.5 bg-indigo-100 text-indigo-700 text-xs rounded">{{ __('ui.admin_badge') }}</span>
                            @else
                                <span class="text-gray-500">{{ __('ui.user') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm">
                            @if($user->force_password_reset)
                                <span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 text-xs rounded">{{ __('ui.pending') }}</span>
                            @else
                                <span class="text-green-600 text-xs">{{ __('ui.done') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right text-sm space-x-2">
                            <a href="{{ route('admin.users.edit', $user) }}" class="text-gray-600 hover:text-gray-900">{{ __('ui.edit') }}</a>
                            <form method="POST" action="{{ route('admin.users.reset-password', $user) }}" class="inline" onsubmit="return confirm('{{ __('ui.confirm_reset_password', ['name' => $user->name]) }}')">
                                @csrf
                                <button type="submit" class="text-yellow-600 hover:underline">{{ __('ui.reset_pw') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>
