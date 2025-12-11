<x-filament-panels::page>
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
        {{-- Main Column: Checklist Items --}}
        <div class="xl:col-span-2">
            {{-- General Tasks Section --}}
            <x-filament::section style="margin-bottom: 2rem;">
                <x-slot name="heading">General Tasks</x-slot>

                <div class="fi-ta-content rounded-lg bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                    <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 text-start dark:divide-white/5">
                        <tbody class="divide-y divide-gray-200 whitespace-nowrap dark:divide-white/5">
                            {{-- Review Invoices --}}
                            @php $isInvoicesReviewed = $this->record->items['invoices_reviewed'] ?? false; @endphp
                            <tr class="fi-ta-row {{ $isInvoicesReviewed ? 'bg-success-50 dark:bg-success-400/10' : '' }} transition duration-75">
                                <td class="fi-ta-cell px-3 py-4 w-12">
                                    <button
                                        wire:click="toggleGeneralItem('invoices_reviewed')"
                                        class="flex items-center justify-center w-8 h-8 rounded-lg transition-colors {{ $isInvoicesReviewed ? 'text-success-600 bg-success-50 hover:bg-success-100 dark:text-success-400 dark:bg-success-400/10 dark:hover:bg-success-400/20' : 'text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:text-gray-500 dark:hover:text-gray-400 dark:hover:bg-white/5' }}"
                                    >
                                        <x-heroicon-o-check-circle class="w-6 h-6" />
                                    </button>
                                </td>
                                <td class="fi-ta-cell px-3 py-4">
                                    <div class="fi-ta-text">
                                        <span class="fi-ta-text-item {{ $isInvoicesReviewed ? 'line-through text-gray-400 dark:text-gray-500' : 'text-gray-950 dark:text-white' }}">
                                            Review unpaid/unsent invoices
                                        </span>
                                    </div>
                                </td>
                                <td class="fi-ta-cell px-3 py-4 text-end">
                                    <x-filament::badge color="gray" size="sm">
                                        {{ $this->pendingInvoices->count() }} pending
                                    </x-filament::badge>
                                </td>
                            </tr>

                            {{-- Check Bank Statements --}}
                            @php $isBankStatementsChecked = $this->record->items['bank_statements_checked'] ?? false; @endphp
                            <tr class="fi-ta-row {{ $isBankStatementsChecked ? 'bg-success-50 dark:bg-success-400/10' : '' }} transition duration-75">
                                <td class="fi-ta-cell px-3 py-4 w-12">
                                    <button
                                        wire:click="toggleGeneralItem('bank_statements_checked')"
                                        class="flex items-center justify-center w-8 h-8 rounded-lg transition-colors {{ $isBankStatementsChecked ? 'text-success-600 bg-success-50 hover:bg-success-100 dark:text-success-400 dark:bg-success-400/10 dark:hover:bg-success-400/20' : 'text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:text-gray-500 dark:hover:text-gray-400 dark:hover:bg-white/5' }}"
                                    >
                                        <x-heroicon-o-check-circle class="w-6 h-6" />
                                    </button>
                                </td>
                                <td class="fi-ta-cell px-3 py-4">
                                    <div class="fi-ta-text">
                                        <span class="fi-ta-text-item {{ $isBankStatementsChecked ? 'line-through text-gray-400 dark:text-gray-500' : 'text-gray-950 dark:text-white' }}">
                                            Check bank statements for business payments
                                        </span>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $this->activeBankAccounts->pluck('name')->join(', ') }}</p>
                                    </div>
                                </td>
                                <td class="fi-ta-cell px-3 py-4 text-end">
                                    <x-filament::badge color="gray" size="sm">
                                        {{ $this->activeBankAccounts->count() }} accounts
                                    </x-filament::badge>
                                </td>
                            </tr>

                            {{-- Review Other Incomes --}}
                            @php $isOtherIncomesReviewed = $this->record->items['other_incomes_reviewed'] ?? false; @endphp
                            <tr class="fi-ta-row {{ $isOtherIncomesReviewed ? 'bg-success-50 dark:bg-success-400/10' : '' }} transition duration-75">
                                <td class="fi-ta-cell px-3 py-4 w-12">
                                    <button
                                        wire:click="toggleGeneralItem('other_incomes_reviewed')"
                                        class="flex items-center justify-center w-8 h-8 rounded-lg transition-colors {{ $isOtherIncomesReviewed ? 'text-success-600 bg-success-50 hover:bg-success-100 dark:text-success-400 dark:bg-success-400/10 dark:hover:bg-success-400/20' : 'text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:text-gray-500 dark:hover:text-gray-400 dark:hover:bg-white/5' }}"
                                    >
                                        <x-heroicon-o-check-circle class="w-6 h-6" />
                                    </button>
                                </td>
                                <td class="fi-ta-cell px-3 py-4">
                                    <div class="fi-ta-text">
                                        <span class="fi-ta-text-item {{ $isOtherIncomesReviewed ? 'line-through text-gray-400 dark:text-gray-500' : 'text-gray-950 dark:text-white' }}">
                                            Review and mark other incomes as paid
                                        </span>
                                    </div>
                                </td>
                                <td class="fi-ta-cell px-3 py-4 text-end">
                                    <x-filament::badge color="gray" size="sm">
                                        {{ $this->pendingOtherIncomes->count() }} pending
                                    </x-filament::badge>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            {{-- Suppliers Section --}}
            @if(count($this->suppliers) > 0)
                <x-filament::section style="margin-bottom: 2rem;">
                    <x-slot name="heading">Expected Bills from Suppliers</x-slot>
                    <x-slot name="description">Upload bills from these suppliers for {{ $this->record->period_name }}</x-slot>

                    <div class="fi-ta-content rounded-lg bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                        <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 text-start dark:divide-white/5">
                            <thead class="bg-gray-50 dark:bg-white/5">
                                <tr>
                                    <th class="fi-ta-header-cell px-3 py-3.5 w-12"></th>
                                    <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Supplier</th>
                                    <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Frequency</th>
                                    <th class="fi-ta-header-cell px-3 py-3.5 text-end text-sm font-semibold text-gray-950 dark:text-white">Last Bill</th>
                                    <th class="fi-ta-header-cell px-3 py-3.5 w-24"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 whitespace-nowrap dark:divide-white/5">
                                @foreach($this->suppliers as $supplierId => $data)
                                    @php
                                        $supplier = $data['model'];
                                        $status = $data['status'];
                                        $lastBill = $data['last_bill'] ?? null;
                                        $isCompleted = $status['completed'] ?? false;
                                        $isSkipped = $status['skipped'] ?? false;
                                        $isDone = $isCompleted || $isSkipped;
                                    @endphp
                                    <tr class="fi-ta-row {{ $isCompleted ? 'bg-success-50 dark:bg-success-400/10' : ($isSkipped ? 'opacity-50' : '') }} transition duration-75">
                                        <td class="fi-ta-cell px-3 py-4 w-12">
                                            <button
                                                wire:click="toggleSupplier({{ $supplierId }})"
                                                class="flex items-center justify-center w-8 h-8 rounded-lg transition-colors {{ $isCompleted ? 'text-success-600 bg-success-50 hover:bg-success-100 dark:text-success-400 dark:bg-success-400/10 dark:hover:bg-success-400/20' : 'text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:text-gray-500 dark:hover:text-gray-400 dark:hover:bg-white/5' }}"
                                            >
                                                @if($isSkipped)
                                                    <x-heroicon-o-minus-circle class="w-6 h-6" />
                                                @else
                                                    <x-heroicon-o-check-circle class="w-6 h-6" />
                                                @endif
                                            </button>
                                        </td>
                                        <td class="fi-ta-cell px-3 py-4">
                                            <div class="fi-ta-text">
                                                <span class="fi-ta-text-item {{ $isDone ? 'line-through text-gray-400 dark:text-gray-500' : 'text-gray-950 dark:text-white' }} font-medium">
                                                    {{ $supplier->name }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="fi-ta-cell px-3 py-4">
                                            <x-filament::badge color="gray" size="sm">
                                                {{ $supplier->billing_frequency->label() }}
                                                @if($supplier->billing_month)
                                                    ({{ $supplier->getBillingMonthName() }})
                                                @endif
                                            </x-filament::badge>
                                        </td>
                                        <td class="fi-ta-cell px-3 py-4 text-end">
                                            @if($lastBill)
                                                <span class="text-sm font-medium text-gray-950 dark:text-white">{{ Number::currency($lastBill->total_amount / 100, $lastBill->currency) }}</span>
                                                <span class="text-xs text-gray-500 dark:text-gray-400 block">{{ $lastBill->bill_date->format('M Y') }}</span>
                                            @else
                                                <span class="text-xs text-gray-400 dark:text-gray-500">No previous</span>
                                            @endif
                                        </td>
                                        <td class="fi-ta-cell px-3 py-4">
                                            <div class="flex items-center justify-end gap-1">
                                                @if(!$isDone)
                                                    <x-filament::icon-button
                                                        icon="heroicon-o-forward"
                                                        color="gray"
                                                        size="sm"
                                                        wire:click="skipSupplier({{ $supplierId }})"
                                                        tooltip="Skip this month"
                                                    />
                                                @endif
                                                <x-filament::icon-button
                                                    icon="heroicon-o-arrow-top-right-on-square"
                                                    color="gray"
                                                    size="sm"
                                                    tag="a"
                                                    :href="route('filament.admin.resources.suppliers.edit', $supplier)"
                                                    tooltip="View supplier"
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            @endif

            {{-- Income Sources Section --}}
            @if(count($this->incomeSources) > 0)
                <x-filament::section style="margin-bottom: 2rem;">
                    <x-slot name="heading">Expected Income Sources</x-slot>
                    <x-slot name="description">Upload income records from these sources for {{ $this->record->period_name }}</x-slot>

                    <div class="fi-ta-content rounded-lg bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                        <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 text-start dark:divide-white/5">
                            <thead class="bg-gray-50 dark:bg-white/5">
                                <tr>
                                    <th class="fi-ta-header-cell px-3 py-3.5 w-12"></th>
                                    <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Source</th>
                                    <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Frequency</th>
                                    <th class="fi-ta-header-cell px-3 py-3.5 w-24"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 whitespace-nowrap dark:divide-white/5">
                                @foreach($this->incomeSources as $incomeSourceId => $data)
                                    @php
                                        $incomeSource = $data['model'];
                                        $status = $data['status'];
                                        $isCompleted = $status['completed'] ?? false;
                                        $isSkipped = $status['skipped'] ?? false;
                                        $isDone = $isCompleted || $isSkipped;
                                    @endphp
                                    <tr class="fi-ta-row {{ $isCompleted ? 'bg-success-50 dark:bg-success-400/10' : ($isSkipped ? 'opacity-50' : '') }} transition duration-75">
                                        <td class="fi-ta-cell px-3 py-4 w-12">
                                            <button
                                                wire:click="toggleIncomeSource({{ $incomeSourceId }})"
                                                class="flex items-center justify-center w-8 h-8 rounded-lg transition-colors {{ $isCompleted ? 'text-success-600 bg-success-50 hover:bg-success-100 dark:text-success-400 dark:bg-success-400/10 dark:hover:bg-success-400/20' : 'text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:text-gray-500 dark:hover:text-gray-400 dark:hover:bg-white/5' }}"
                                            >
                                                @if($isSkipped)
                                                    <x-heroicon-o-minus-circle class="w-6 h-6" />
                                                @else
                                                    <x-heroicon-o-check-circle class="w-6 h-6" />
                                                @endif
                                            </button>
                                        </td>
                                        <td class="fi-ta-cell px-3 py-4">
                                            <div class="fi-ta-text">
                                                <span class="fi-ta-text-item {{ $isDone ? 'line-through text-gray-400 dark:text-gray-500' : 'text-gray-950 dark:text-white' }} font-medium">
                                                    {{ $incomeSource->name }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="fi-ta-cell px-3 py-4">
                                            <x-filament::badge color="gray" size="sm">
                                                {{ $incomeSource->billing_frequency->label() }}
                                                @if($incomeSource->billing_month)
                                                    ({{ $incomeSource->getBillingMonthName() }})
                                                @endif
                                            </x-filament::badge>
                                        </td>
                                        <td class="fi-ta-cell px-3 py-4">
                                            <div class="flex items-center justify-end gap-1">
                                                @if(!$isDone)
                                                    <x-filament::icon-button
                                                        icon="heroicon-o-forward"
                                                        color="gray"
                                                        size="sm"
                                                        wire:click="skipIncomeSource({{ $incomeSourceId }})"
                                                        tooltip="Skip this month"
                                                    />
                                                @endif
                                                <x-filament::icon-button
                                                    icon="heroicon-o-arrow-top-right-on-square"
                                                    color="gray"
                                                    size="sm"
                                                    :href="route('filament.admin.resources.income-sources.edit', $incomeSource)"
                                                    tooltip="View source"
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            @endif
        </div>

        {{-- Side Column: Related Records --}}
        <div>
            {{-- Pending Invoices --}}
            @if($this->pendingInvoices->count() > 0)
                <x-filament::section collapsible collapsed persist-collapsed id="checklist-{{ $this->record->id }}-pending-invoices" compact style="margin-bottom: 1.5rem;">
                    <x-slot name="heading">Pending Invoices ({{ $this->pendingInvoices->count() }})</x-slot>

                    <div class="fi-ta-content rounded-lg bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden -mx-4 -my-3">
                        <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 text-start dark:divide-white/5">
                            <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                                @foreach($this->pendingInvoices as $invoice)
                                    <tr class="fi-ta-row hover:bg-gray-50 dark:hover:bg-white/5 cursor-pointer transition duration-75" onclick="window.location='{{ route('filament.admin.resources.invoices.edit', $invoice) }}'">
                                        <td class="fi-ta-cell px-3 py-2">
                                            <div class="text-sm font-medium text-gray-950 dark:text-white">{{ $invoice->invoice_number ?? 'Draft' }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $invoice->customer?->name ?? 'No customer' }}</div>
                                        </td>
                                        <td class="fi-ta-cell px-3 py-2 text-end">
                                            <div class="text-sm font-medium text-gray-950 dark:text-white">{{ Number::currency($invoice->total_amount / 100, $invoice->currency) }}</div>
                                            <x-filament::badge size="sm" :color="$invoice->status->color()">
                                                {{ $invoice->status->label() }}
                                            </x-filament::badge>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            @endif

            {{-- Pending Other Incomes --}}
            @if($this->pendingOtherIncomes->count() > 0)
                <x-filament::section collapsible collapsed persist-collapsed id="checklist-{{ $this->record->id }}-pending-other-incomes" compact style="margin-bottom: 1.5rem;">
                    <x-slot name="heading">Pending Other Incomes ({{ $this->pendingOtherIncomes->count() }})</x-slot>

                    <div class="fi-ta-content rounded-lg bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden -mx-4 -my-3">
                        <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 text-start dark:divide-white/5">
                            <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                                @foreach($this->pendingOtherIncomes as $otherIncome)
                                    <tr class="fi-ta-row hover:bg-gray-50 dark:hover:bg-white/5 cursor-pointer transition duration-75" onclick="window.location='{{ route('filament.admin.resources.other-incomes.edit', $otherIncome) }}'">
                                        <td class="fi-ta-cell px-3 py-2">
                                            <div class="text-sm font-medium text-gray-950 dark:text-white">{{ $otherIncome->incomeSource?->name ?? 'Unknown' }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $otherIncome->income_date?->format('M d, Y') }}</div>
                                        </td>
                                        <td class="fi-ta-cell px-3 py-2 text-end">
                                            <div class="text-sm font-medium text-gray-950 dark:text-white">{{ Number::currency($otherIncome->amount / 100, $otherIncome->currency) }}</div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            @endif

            {{-- Recent Bills This Month --}}
            @if($this->recentBillsThisMonth->count() > 0)
                <x-filament::section collapsible collapsed persist-collapsed id="checklist-{{ $this->record->id }}-bills-this-month" compact style="margin-bottom: 1.5rem;">
                    <x-slot name="heading">Bills This Month ({{ $this->recentBillsThisMonth->count() }})</x-slot>

                    <div class="fi-ta-content rounded-lg bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden -mx-4 -my-3">
                        <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 text-start dark:divide-white/5">
                            <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                                @foreach($this->recentBillsThisMonth as $bill)
                                    <tr class="fi-ta-row hover:bg-gray-50 dark:hover:bg-white/5 cursor-pointer transition duration-75" onclick="window.location='{{ route('filament.admin.resources.bills.edit', $bill) }}'">
                                        <td class="fi-ta-cell px-3 py-2">
                                            <div class="text-sm font-medium text-gray-950 dark:text-white">{{ $bill->supplier?->name ?? 'Unknown' }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $bill->bill_date?->format('M d, Y') }}</div>
                                        </td>
                                        <td class="fi-ta-cell px-3 py-2 text-end">
                                            <div class="text-sm font-medium text-gray-950 dark:text-white">{{ Number::currency($bill->total_amount / 100, $bill->currency) }}</div>
                                            <x-filament::badge size="sm" :color="$bill->status->color()">
                                                {{ $bill->status->label() }}
                                            </x-filament::badge>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            @endif
        </div>
    </div>
</x-filament-panels::page>
