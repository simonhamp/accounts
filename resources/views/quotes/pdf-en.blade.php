<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Quote {{ $quote->quote_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            margin-bottom: 40px;
        }
        .header-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .header-left {
            display: table-cell;
            width: 55%;
            vertical-align: top;
        }
        .header-right {
            display: table-cell;
            width: 45%;
            vertical-align: top;
            text-align: right;
        }
        .header h1 {
            font-size: 24px;
            margin: 0 0 10px 0;
            color: #000;
        }
        .from-details {
            line-height: 1.6;
            font-size: 11px;
        }
        .from-details strong {
            font-size: 12px;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 8px;
            color: #000;
        }
        .details {
            line-height: 1.6;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table thead {
            background-color: #f5f5f5;
        }
        table th {
            padding: 10px;
            text-align: left;
            font-weight: bold;
            border-bottom: 2px solid #ddd;
        }
        table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .text-right {
            text-align: right;
        }
        .totals {
            margin-top: 20px;
            text-align: right;
        }
        .totals table {
            margin-left: auto;
            width: 300px;
        }
        .totals td {
            padding: 8px;
        }
        .totals .total-row {
            font-weight: bold;
            font-size: 14px;
            border-top: 2px solid #000;
        }
        .accepted-info {
            margin-top: 30px;
            padding: 15px;
            background-color: #e8f5e9;
            border: 2px solid #4caf50;
            border-radius: 5px;
        }
        .accepted-info .accepted-stamp {
            font-size: 18px;
            font-weight: bold;
            color: #2e7d32;
        }
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            color: #666;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-grid">
            <div class="header-left">
                <h1>QUOTE</h1>
                <p><strong>No:</strong> {{ $quote->quote_number }}</p>
                <p><strong>Date:</strong> {{ $quote->quote_date->format('d/m/Y') }}</p>
                @if($quote->valid_until)
                <p><strong>Valid until:</strong> {{ $quote->valid_until->format('d/m/Y') }}</p>
                @endif
            </div>
            <div class="header-right">
                <div class="from-details">
                    <strong>{{ $quote->person->name }}{{ $quote->person->entity_type?->legalSuffix() ? ', '.$quote->person->entity_type->legalSuffix() : '' }}</strong><br>
                    {{ $quote->person->address }}<br>
                    {{ $quote->person->postal_code }}, {{ $quote->person->city }}<br>
                    {{ $quote->person->country }}<br>
                    {{ $quote->person->taxIdentifierLabel() }}: {{ $quote->person->taxIdentifier() }}
                </div>
            </div>
        </div>
    </div>

    @php
        $displayCustomerName = $quote->customer_name ?: $quote->customer?->name;
        $displayCustomerAddress = $quote->customer_address ?: $quote->customer?->address;
    @endphp
    @if($displayCustomerName)
    <div class="section">
        <div class="section-title">PREPARED FOR</div>
        <div class="details">
            <strong>{{ $displayCustomerName }}</strong><br>
            @if($displayCustomerAddress)
                {{ $displayCustomerAddress }}<br>
            @endif
            @if($quote->customer_tax_id)
                <strong>Tax ID:</strong> {{ $quote->customer_tax_id }}<br>
            @endif
        </div>
    </div>
    @endif

    @php
        $showTaxColumn = $quote->hasTaxBreakdown();
    @endphp
    <div class="section">
        <div class="section-title">QUOTE DETAILS</div>
        <table>
            <thead>
                <tr>
                    <th style="width: {{ $showTaxColumn ? '32%' : '40%' }};">Description</th>
                    <th class="text-right" style="width: 10%;">Quantity</th>
                    <th style="width: 10%;">Unit</th>
                    <th class="text-right" style="width: 14%;">Unit Price</th>
                    @if($showTaxColumn)
                        <th class="text-right" style="width: 10%;">Tax</th>
                    @endif
                    <th class="text-right" style="width: {{ $showTaxColumn ? '16%' : '20%' }};">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quote->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="text-right">{{ rtrim(rtrim(number_format($item->quantity, 4, '.', ','), '0'), '.') }}</td>
                    <td>{{ $item->unit?->label() ?? 'Units' }}</td>
                    <td class="text-right">{{ number_format($item->unit_price / 100, 2, '.', ',') }} {{ $quote->currency }}</td>
                    @if($showTaxColumn)
                        <td class="text-right">
                            @if($item->tax_type)
                                {{ $item->tax_type->labelEn() }}{{ $item->tax_type->isTaxable() ? ' '.rtrim(rtrim(number_format((float) $item->tax_rate, 2, '.', ','), '0'), '.').'%' : '' }}
                            @else
                                &mdash;
                            @endif
                        </td>
                    @endif
                    <td class="text-right">{{ number_format($item->total / 100, 2, '.', ',') }} {{ $quote->currency }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="totals">
        <table>
            @if($showTaxColumn)
                <tr>
                    <td>Subtotal:</td>
                    <td class="text-right">{{ number_format($quote->tax_base_total / 100, 2, '.', ',') }} {{ $quote->currency }}</td>
                </tr>
                @foreach($quote->taxBreakdown() as $group)
                    @if($group['type']?->isTaxable())
                        <tr>
                            <td>{{ $group['type']->labelEn() }} {{ rtrim(rtrim(number_format($group['rate'], 2, '.', ','), '0'), '.') }}%:</td>
                            <td class="text-right">{{ number_format($group['tax'] / 100, 2, '.', ',') }} {{ $quote->currency }}</td>
                        </tr>
                    @endif
                @endforeach
                @if($quote->irpf_rate && $quote->irpf_amount)
                    <tr>
                        <td>IRPF Withholding {{ rtrim(rtrim(number_format((float) $quote->irpf_rate, 2, '.', ','), '0'), '.') }}%:</td>
                        <td class="text-right">-{{ number_format($quote->irpf_amount / 100, 2, '.', ',') }} {{ $quote->currency }}</td>
                    </tr>
                @endif
            @endif
            <tr class="total-row">
                <td><strong>TOTAL:</strong></td>
                <td class="text-right">
                    <strong>{{ number_format($quote->total_amount / 100, 2, '.', ',') }} {{ $quote->currency }}</strong>
                </td>
            </tr>
            @if($quote->currency !== 'EUR' && $quote->amount_eur)
            <tr>
                <td style="font-size: 11px; color: #666;">EUR Equivalent:</td>
                <td class="text-right" style="font-size: 11px; color: #666;">
                    {{ number_format($quote->amount_eur / 100, 2, '.', ',') }} EUR
                </td>
            </tr>
            @endif
        </table>
    </div>

    @if($quote->requiresIgicReverseChargeNote())
    <div class="section" style="margin-top: 20px; padding: 10px; border: 1px solid #ddd; font-size: 11px; color: #333;">
        <strong>Transaction not subject to IGIC. Reverse charge applies (inversión del sujeto pasivo).</strong>
    </div>
    @endif

    @if($quote->legal_notes)
    <div class="section" style="margin-top: 20px; font-size: 11px; color: #555;">
        {!! nl2br(e($quote->legal_notes)) !!}
    </div>
    @endif

    @if($quote->isAccepted() || $quote->isInvoiced())
    <div class="accepted-info">
        <div class="accepted-stamp">ACCEPTED</div>
    </div>
    @endif

    <div class="footer">
        @if($quote->person->isLegalEntity())
            @if($quote->person->registro_mercantil)
                <p>{{ $quote->person->registro_mercantil }}</p>
            @endif
            @if($quote->person->cif)
                <p>CIF: {{ $quote->person->cif }}</p>
            @endif
        @endif
        <p>This document is a quote and is not a valid invoice.</p>
        <p>Quote generated on {{ $quote->generated_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }}</p>
    </div>
</body>
</html>
