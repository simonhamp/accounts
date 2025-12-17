<x-layouts.app.sidebar :title="$selectedPerson ? $selectedPerson->name . ' - ' . __('records.title') : __('records.title')">
    <flux:main class="p-6">
        <div class="max-w-7xl mx-auto">
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white mb-6">{{ $selectedPerson ? $selectedPerson->name : __('records.title') }}</h1>

            @if($people->isEmpty())
                <div class="bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 p-8 text-center">
                    <p class="text-zinc-500 dark:text-zinc-400">{{ __('records.no_people') }}</p>
                </div>
            @elseif($selectedPerson)
                    @if($years->isEmpty())
                        <div class="bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 p-8 text-center">
                            <p class="text-zinc-500 dark:text-zinc-400">{{ __('records.no_records_for_person', ['name' => $selectedPerson->name]) }}</p>
                        </div>
                    @else
                        <div class="mb-6">
                            <div class="border-b border-zinc-200 dark:border-zinc-700">
                                <nav class="-mb-px flex space-x-8" aria-label="Years">
                                    @foreach($years as $year)
                                        <a
                                            href="{{ route('records.index', ['person' => $selectedPerson->id, 'year' => $year]) }}"
                                            class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm {{ $selectedYear === $year ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-300' }}"
                                        >
                                            {{ $year }}
                                        </a>
                                    @endforeach
                                </nav>
                            </div>
                        </div>

                        @if($records->isEmpty())
                            <div class="bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 p-8 text-center">
                                <p class="text-zinc-500 dark:text-zinc-400">{{ __('records.no_records_for_year', ['year' => $selectedYear]) }}</p>
                            </div>
                        @else
                            @php
                                $totals = $records->groupBy('currency')->map(function ($items, $currency) {
                                    $income = $items->where('is_income', true)->sum('amount');
                                    $outgoing = $items->where('is_income', false)->sum('amount');
                                    return [
                                        'income' => $income,
                                        'outgoing' => $outgoing,
                                        'net' => $income - $outgoing,
                                    ];
                                });

                                // Calculate EUR totals
                                $eurIncomeTotal = $records->where('is_income', true)->sum('amount_eur');
                                $eurOutgoingTotal = $records->where('is_income', false)->sum('amount_eur');
                                $eurNetTotal = $eurIncomeTotal - $eurOutgoingTotal;
                            @endphp

                            @if($selectedYear >= 2023)
                            <div class="mb-6 flex items-start gap-4">
                                <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-4">
                                    @foreach($totals as $currency => $total)
                                        <div class="bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                                            <h3 class="text-lg font-medium text-zinc-900 dark:text-white mb-3">{{ __('records.summary', ['currency' => $currency]) }}</h3>
                                            <dl class="space-y-2">
                                                <div class="flex justify-between">
                                                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('records.income') }}</dt>
                                                    <dd class="text-sm font-medium text-green-600 dark:text-green-400">+{{ number_format($total['income'] / 100, 2) }}</dd>
                                                </div>
                                                <div class="flex justify-between">
                                                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('records.outgoing') }}</dt>
                                                    <dd class="text-sm font-medium text-red-600 dark:text-red-400">-{{ number_format($total['outgoing'] / 100, 2) }}</dd>
                                                </div>
                                                <div class="flex justify-between pt-2 border-t border-zinc-200 dark:border-zinc-700">
                                                    <dt class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('records.net') }}</dt>
                                                    <dd class="text-sm font-bold {{ $total['net'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                                        {{ $total['net'] >= 0 ? '+' : '' }}{{ number_format($total['net'] / 100, 2) }}
                                                    </dd>
                                                </div>
                                            </dl>
                                        </div>
                                    @endforeach
                                </div>
                                <a
                                    href="{{ route('records.download', ['person' => $selectedPerson->id, 'year' => $selectedYear]) }}"
                                    class="flex-shrink-0 inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    {{ __('records.download_all') }}
                                </a>
                            </div>
                            @endif

                            <div class="bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                                    <thead class="bg-zinc-50 dark:bg-zinc-800">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                                                {{ __('records.date') }}
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                                                {{ __('records.type') }}
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                                                {{ __('records.description') }}
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                                                {{ __('records.amount') }}
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                                                {{ __('records.amount_eur') }}
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                                                {{ __('records.download') }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-200 dark:divide-zinc-700">
                                        @foreach($records as $record)
                                            <tr class="{{ $record['is_income'] ? 'bg-green-50 dark:bg-green-900/10' : 'bg-red-50 dark:bg-red-900/10' }}">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-zinc-900 dark:text-zinc-100">
                                                    {{ $record['date']->format('d/m/Y') }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                    @if($record['type'] === 'invoice')
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                            {{ __('records.invoice') }}
                                                        </span>
                                                    @elseif($record['type'] === 'other_income')
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                                            {{ __('records.other_income') }}
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">
                                                            {{ __('records.bill') }}
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 text-sm text-zinc-900 dark:text-zinc-100">
                                                    {{ $record['description'] }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium {{ $record['is_income'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                                    {{ $record['is_income'] ? '+' : '-' }}{{ number_format($record['amount'] / 100, 2) }} {{ $record['currency'] }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium {{ $record['is_income'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                                    @if($record['amount_eur'])
                                                        {{ $record['is_income'] ? '+' : '-' }}{{ number_format($record['amount_eur'] / 100, 2) }} EUR
                                                    @else
                                                        <span class="text-zinc-400 dark:text-zinc-600">-</span>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                                    @if($record['download_url'])
                                                        <a
                                                            href="{{ $record['download_url'] }}"
                                                            class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                                            target="_blank"
                                                        >
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                            </svg>
                                                        </a>
                                                    @else
                                                        <span class="text-zinc-400 dark:text-zinc-600">-</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="bg-zinc-100 dark:bg-zinc-800">
                                        <tr>
                                            <td colspan="4" class="px-6 py-4 text-sm font-bold text-zinc-900 dark:text-white text-right">
                                                {{ __('records.total_eur') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold {{ $eurNetTotal >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                                {{ $eurNetTotal >= 0 ? '+' : '' }}{{ number_format($eurNetTotal / 100, 2) }} EUR
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @endif
                    @endif
            @endif
        </div>
    </flux:main>
</x-layouts.app.sidebar>
