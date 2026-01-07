<x-filament-panels::page>
    <style>
        .earnings-table {
            width: 100%;
            font-size: 0.875rem;
            border-collapse: collapse;
        }
        .earnings-table th,
        .earnings-table td {
            padding: 0.5rem 0.75rem;
        }
        .earnings-table thead tr {
            border-bottom: 1px solid rgb(229, 231, 235);
        }
        .dark .earnings-table thead tr {
            border-color: rgb(55, 65, 81);
        }
        .earnings-table th {
            font-weight: 600;
            color: rgb(3, 7, 18);
        }
        .dark .earnings-table th {
            color: white;
        }
        .earnings-table th.text-right,
        .earnings-table td.text-right {
            text-align: right;
        }
        .earnings-table th.text-center {
            text-align: center;
        }
        .earnings-table th .subheading {
            font-size: 0.75rem;
            font-weight: 400;
            color: rgb(107, 114, 128);
        }
        .earnings-table tbody tr {
            border-bottom: 1px solid rgb(229, 231, 235);
        }
        .dark .earnings-table tbody tr {
            border-color: rgb(55, 65, 81);
        }
        .earnings-table td {
            color: rgb(55, 65, 81);
            font-variant-numeric: tabular-nums;
        }
        .dark .earnings-table td {
            color: rgb(209, 213, 219);
        }
        .earnings-table .year-total {
            background-color: rgb(243, 244, 246);
            font-weight: 600;
        }
        .dark .earnings-table .year-total {
            background-color: rgb(31, 41, 55);
        }
        .earnings-table .year-total td:first-child {
            font-weight: 700;
        }
        .earnings-table .text-negative {
            color: rgb(220, 38, 38);
        }
        .dark .earnings-table .text-negative {
            color: rgb(248, 113, 113);
        }
        .earnings-table .text-muted {
            color: rgb(156, 163, 175);
        }
        .earnings-table .text-warning {
            color: rgb(217, 119, 6);
        }
        .dark .earnings-table .text-warning {
            color: rgb(251, 191, 36);
        }
        .earnings-table .text-success {
            color: rgb(22, 163, 74);
        }
        .dark .earnings-table .text-success {
            color: rgb(74, 222, 128);
        }
        .earnings-table .border-left {
            border-left: 1px solid rgb(229, 231, 235);
        }
        .dark .earnings-table .border-left {
            border-color: rgb(55, 65, 81);
        }
        .settlement-arrow {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        .settlement-arrow svg {
            width: 1rem;
            height: 1rem;
        }
    </style>

    <x-filament::section>
        <x-slot name="heading">Filter</x-slot>
        <form wire:submit.prevent>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                <div>
                    <label class="fi-fo-field-wrp-label" style="display: inline-flex; align-items: center; gap: 0.75rem; font-size: 0.875rem; font-weight: 500; line-height: 1.5rem;">Year</label>
                    <select
                        wire:model.live="year"
                        class="fi-select-input"
                        style="display: block; width: 100%; border-radius: 0.5rem; border: none; padding: 0.375rem 2rem 0.375rem 0.75rem; font-size: 0.875rem; box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05); outline: none; ring: 1px solid rgba(0, 0, 0, 0.1);"
                    >
                        @foreach(range(date('Y'), 2023) as $y)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </form>
    </x-filament::section>

    @php
        $data = $this->getMonthlyData();
        $people = $this->getPeople();
    @endphp

    <x-filament::section>
        <x-slot name="heading">Monthly Breakdown - {{ $year }}</x-slot>
        <x-slot name="description">Net earnings (invoices + other income - bills) and settlement amounts for 50/50 split</x-slot>

        <div style="overflow-x: auto;">
            <table class="earnings-table">
                <thead>
                    <tr>
                        <th style="text-align: left;">Month</th>
                        @foreach($people as $person)
                            <th class="text-right">
                                {{ explode(' ', $person->name)[0] }}<br>
                                <span class="subheading">Net Earnings</span>
                            </th>
                        @endforeach
                        <th class="text-right">
                            Combined<br>
                            <span class="subheading">Total</span>
                        </th>
                        <th class="text-right">
                            Fair Share<br>
                            <span class="subheading">50% each</span>
                        </th>
                        <th style="text-align: left;">Settlement</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data as $row)
                        <tr class="{{ $row['month'] === 13 ? 'year-total' : '' }}">
                            <td>{{ $row['month_name'] }}</td>
                            @foreach($people as $person)
                                @php
                                    $net = $row['person_' . $person->id . '_net'] ?? 0;
                                @endphp
                                <td class="text-right {{ $net < 0 ? 'text-negative' : '' }}">
                                    @if($net != 0)
                                        {{ number_format($net / 100, 2) }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="text-right">
                                @if($row['total_earnings'] != 0)
                                    {{ number_format($row['total_earnings'] / 100, 2) }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-right">
                                @if($row['fair_share'] != 0)
                                    {{ number_format($row['fair_share'] / 100, 2) }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($row['settlement'] !== 'Already balanced')
                                    <span class="settlement-arrow text-warning">
                                        <x-heroicon-m-arrow-right />
                                        {{ $row['settlement'] }}
                                    </span>
                                @else
                                    <span class="text-success">{{ $row['settlement'] }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Detailed Breakdown</x-slot>
        <x-slot name="description">Invoices, other income, and bills per person per month</x-slot>

        <div style="overflow-x: auto;">
            <table class="earnings-table">
                <thead>
                    <tr>
                        <th style="text-align: left;">Month</th>
                        @foreach($people as $person)
                            <th colspan="3" class="text-center border-left">
                                {{ explode(' ', $person->name)[0] }}
                            </th>
                        @endforeach
                    </tr>
                    <tr>
                        <th></th>
                        @foreach($people as $person)
                            <th class="text-right border-left" style="font-size: 0.75rem; font-weight: 400;">Invoices</th>
                            <th class="text-right" style="font-size: 0.75rem; font-weight: 400;">Other</th>
                            <th class="text-right" style="font-size: 0.75rem; font-weight: 400;">Bills</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($data as $row)
                        <tr class="{{ $row['month'] === 13 ? 'year-total' : '' }}">
                            <td>{{ $row['month_name'] }}</td>
                            @foreach($people as $person)
                                @php
                                    $invoices = $row['person_' . $person->id . '_invoices'] ?? 0;
                                    $other = $row['person_' . $person->id . '_other_income'] ?? 0;
                                    $bills = $row['person_' . $person->id . '_bills'] ?? 0;
                                @endphp
                                <td class="text-right border-left">
                                    @if($invoices != 0)
                                        {{ number_format($invoices / 100, 2) }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if($other != 0)
                                        {{ number_format($other / 100, 2) }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-right text-negative">
                                    @if($bills != 0)
                                        ({{ number_format($bills / 100, 2) }})
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
