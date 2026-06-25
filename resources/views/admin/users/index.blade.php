<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('ui.manage_users') }}</h2>
            <div class="flex items-center gap-3">
                <label class="flex items-center cursor-pointer">
                    <input
                        type="checkbox"
                        id="showTrashedToggle"
                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        checked
                    >
                    <span class="ml-2 text-sm text-gray-700">{{ __('ui.show_deleted_users') }}</span>
                </label>
                <a href="{{ route('admin.users.create') }}" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700">{{ __('ui.new_user') }}</a>
            </div>
        </div>
    </x-slot>

    @if (session('error'))
        <div class="mb-4 p-4 bg-red-100 border border-red-300 text-red-800 rounded">{{ session('error') }}</div>
    @endif

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
            <tbody class="divide-y divide-gray-200" id="usersTableBody">
                @foreach($users as $user)
                    <tr
                        class="user-row {{ $user->trashed() ? 'trashed-user bg-gray-50 opacity-75' : '' }}"
                        data-trashed="{{ $user->trashed() ? '1' : '0' }}"
                    >
                        <td class="px-6 py-4 font-medium">
                            <div class="flex items-center gap-2 flex-wrap">
                                @if($user->trashed())
                                    <span class="text-gray-700 line-through">{{ $user->name }}</span>
                                    <span class="px-2 py-0.5 bg-red-100 text-red-700 text-xs rounded">{{ __('ui.deleted') }}</span>
                                @else
                                    <a href="{{ route('admin.users.show', $user) }}" class="text-indigo-600 hover:underline">{{ $user->name }}</a>
                                @endif
                            </div>
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
                            @if($user->trashed())
                                <form method="POST" action="{{ route('admin.users.restore', $user->id) }}" class="inline" onsubmit="return confirm('{{ __('ui.confirm_restore_user', ['name' => $user->name]) }}')">
                                    @csrf
                                    <button type="submit" class="text-green-600 hover:underline">{{ __('ui.restore') }}</button>
                                </form>
                                <span class="text-gray-400">{{ __('ui.deleted_ago', ['time' => $user->deleted_at->diffForHumans()]) }}</span>
                            @else
                                <a href="{{ route('admin.users.edit', $user) }}" class="text-gray-600 hover:text-gray-900">{{ __('ui.edit') }}</a>
                                <form method="POST" action="{{ route('admin.users.reset-password', $user) }}" class="inline" onsubmit="return confirm('{{ __('ui.confirm_reset_password', ['name' => $user->name]) }}')">
                                    @csrf
                                    <button type="submit" class="text-yellow-600 hover:underline">{{ __('ui.reset_pw') }}</button>
                                </form>
                                @if($user->id !== auth()->id())
                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="inline" onsubmit="return confirm('{{ __('ui.confirm_delete_user', ['name' => $user->name]) }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:underline">{{ __('ui.delete') }}</button>
                                    </form>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggle = document.getElementById('showTrashedToggle');
            const tableBody = document.getElementById('usersTableBody');

            if (toggle && tableBody) {
                const stored = localStorage.getItem('showTrashedUsers');
                if (stored !== null) {
                    toggle.checked = stored === 'true';
                }

                updateVisibility();

                toggle.addEventListener('change', function() {
                    localStorage.setItem('showTrashedUsers', this.checked);
                    updateVisibility();
                });

                function updateVisibility() {
                    const rows = tableBody.querySelectorAll('.trashed-user');
                    rows.forEach(row => {
                        row.style.display = toggle.checked ? '' : 'none';
                    });
                }
            }
        });
    </script>
</x-app-layout>
