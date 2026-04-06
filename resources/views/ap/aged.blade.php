<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('ui.aged_ap_title') }}
        </h2>
    </x-slot>

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

        {{-- As-of date filter --}}
        <form method="GET" action="{{ route('ap.aged') }}" class="flex items-center gap-3 mb-6">
            <label class="text-sm font-medium text-gray-700">{{ __('ui.aged_ap_as_of') }}</label>
            <x-text-input type="date" name="as_of"
                value="{{ $report['as_of']->toDateString() }}"
                class="text-sm" />
            <x-primary-button type="submit">{{ __('ui.aged_ap_run') }}</x-primary-button>
        </form>

        @if(empty($report['vendors']))
            <p class="text-gray-500">{{ __('ui.aged_ap_no_data') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm border border-gray-200 rounded">
                    <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                        <tr>
                            <th class="px-4 py-3 text-left">{{ __('ui.aged_ap_vendor') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('ui.aged_bucket_current') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('ui.aged_bucket_1_30') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('ui.aged_bucket_31_60') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('ui.aged_bucket_61_90') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('ui.aged_bucket_over_90') }}</th>
                            <th class="px-4 py-3 text-right font-semibold">{{ __('ui.aged_ap_total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($report['vendors'] as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-medium text-gray-800">
                                    {{ $row['vendor']->name }}
                                    <span class="text-xs text-gray-400 ml-1">{{ $row['vendor']->code }}</span>
                                </td>
                                <td class="px-4 py-2 text-right text-gray-700">{{ number_format($row['current'], 2) }}</td>
                                <td class="px-4 py-2 text-right {{ $row['1_30'] > 0 ? 'text-yellow-600' : 'text-gray-700' }}">{{ number_format($row['1_30'], 2) }}</td>
                                <td class="px-4 py-2 text-right {{ $row['31_60'] > 0 ? 'text-orange-600' : 'text-gray-700' }}">{{ number_format($row['31_60'], 2) }}</td>
                                <td class="px-4 py-2 text-right {{ $row['61_90'] > 0 ? 'text-red-500' : 'text-gray-700' }}">{{ number_format($row['61_90'], 2) }}</td>
                                <td class="px-4 py-2 text-right {{ $row['over_90'] > 0 ? 'text-red-700 font-semibold' : 'text-gray-700' }}">{{ number_format($row['over_90'], 2) }}</td>
                                <td class="px-4 py-2 text-right font-semibold text-gray-900">{{ number_format($row['total'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 border-t-2 border-gray-300 font-bold text-gray-800">
                        <tr>
                            <td class="px-4 py-3">{{ __('ui.aged_ap_totals') }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($report['totals']['current'], 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($report['totals']['1_30'], 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($report['totals']['31_60'], 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($report['totals']['61_90'], 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($report['totals']['over_90'], 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($report['totals']['total'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <p class="mt-3 text-xs text-gray-400">
                {{ __('ui.aged_ap_note', ['date' => $report['as_of']->toFormattedDateString()]) }}
            </p>
        @endif
    </div>
</x-app-layout>
