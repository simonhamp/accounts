<x-filament-panels::page>
    <div x-data="{ tab: $persist('pdf').as('import-other-income-tab') }">
        <x-filament::tabs label="Import type">
            <x-filament::tabs.item
                x-bind:class="tab === 'pdf' && 'fi-active'"
                x-on:click="tab = 'pdf'"
            >
                <x-slot name="icon">
                    <x-filament::icon icon="heroicon-o-document" class="h-5 w-5" />
                </x-slot>
                PDF Documents
            </x-filament::tabs.item>

            <x-filament::tabs.item
                x-bind:class="tab === 'csv' && 'fi-active'"
                x-on:click="tab = 'csv'"
            >
                <x-slot name="icon">
                    <x-filament::icon icon="heroicon-o-table-cells" class="h-5 w-5" />
                </x-slot>
                CSV Payouts
            </x-filament::tabs.item>
        </x-filament::tabs>

        <div class="mt-6">
            {{-- PDF Tab --}}
            <div x-show="tab === 'pdf'" x-cloak>
                <form wire:submit="queuePdfs">
                    {{ $this->pdfForm }}

                    <div class="mt-6">
                        <x-filament::button type="submit" icon="heroicon-o-queue-list">
                            Queue for Processing
                        </x-filament::button>
                    </div>
                </form>
            </div>

            {{-- CSV Tab --}}
            <div x-show="tab === 'csv'" x-cloak>
                <form wire:submit="analyzeCsv">
                    {{ $this->csvForm }}

                    @if($this->csvPreviewData)
                        <x-filament::section class="mt-6">
                            <x-slot name="heading">Analysis Results</x-slot>

                            @if($this->originalFilename)
                                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                                    <span class="font-medium">Source file:</span>
                                    <code class="ml-1 rounded bg-gray-100 px-2 py-0.5 dark:bg-gray-800">{{ $this->originalFilename }}</code>
                                </p>
                            @endif

                            <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Platform Detected</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $this->csvPreviewData['platform'] }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Records Found</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $this->csvPreviewData['total_count'] }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Currency</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $this->csvPreviewData['currency'] }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Amount</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                        {{ number_format($this->csvPreviewData['total_amount'] / 100, 2) }}
                                    </dd>
                                </div>
                            </dl>

                            @if($this->suggestedSourceName)
                                <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-900/20">
                                    <p class="text-sm text-blue-800 dark:text-blue-200">
                                        <span class="font-medium">Suggested Income Source:</span>
                                        {{ $this->suggestedSourceName }}
                                        @if(!$this->csvData['income_source_id'])
                                            <span class="ml-2 text-blue-600 dark:text-blue-400">(will be created if not selected above)</span>
                                        @endif
                                    </p>
                                </div>
                            @endif

                            @if(count($this->csvPreviewData['payouts']) > 0)
                                <div class="mt-4">
                                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Preview (first 5 records)</h4>
                                    <div class="mt-2 overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                            <thead>
                                                <tr>
                                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Date</th>
                                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Description</th>
                                                    <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Amount</th>
                                                    <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Reference</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                                @foreach(array_slice($this->csvPreviewData['payouts'], 0, 5) as $payout)
                                                    <tr>
                                                        <td class="whitespace-nowrap px-3 py-2 text-sm text-gray-900 dark:text-white">
                                                            {{ $payout['income_date'] ?? '-' }}
                                                        </td>
                                                        <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">
                                                            {{ \Illuminate\Support\Str::limit($payout['description'] ?? '-', 40) }}
                                                        </td>
                                                        <td class="whitespace-nowrap px-3 py-2 text-right text-sm text-gray-900 dark:text-white">
                                                            {{ $payout['currency'] ?? '' }} {{ number_format(($payout['amount'] ?? 0) / 100, 2) }}
                                                        </td>
                                                        <td class="whitespace-nowrap px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                                                            {{ \Illuminate\Support\Str::limit($payout['reference'] ?? '-', 20) }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif
                        </x-filament::section>
                    @endif

                    <div class="mt-6 flex gap-3">
                        @if(! $this->csvPreviewData)
                            <x-filament::button type="submit" color="gray" icon="heroicon-o-magnifying-glass">
                                Analyze CSV
                            </x-filament::button>
                        @else
                            <x-filament::button type="button" wire:click="importCsvRecords" icon="heroicon-o-arrow-down-tray">
                                Import Records
                            </x-filament::button>
                            <x-filament::button type="button" wire:click="resetCsvForm" color="danger" icon="heroicon-o-x-mark">
                                Start Over
                            </x-filament::button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-filament-panels::page>
