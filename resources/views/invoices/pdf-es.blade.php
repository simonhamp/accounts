<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $invoice->isCreditNote() ? 'Nota de Crédito' : 'Factura' }} {{ $invoice->invoice_number }}</title>
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
        .payment-info {
            margin-top: 30px;
            padding: 15px;
            background-color: #e8f5e9;
            border: 2px solid #4caf50;
            border-radius: 5px;
        }
        .payment-info .paid-stamp {
            font-size: 18px;
            font-weight: bold;
            color: #2e7d32;
            margin-bottom: 10px;
        }
        .payment-info table {
            margin-top: 10px;
            background: transparent;
        }
        .payment-info td {
            padding: 5px 10px;
            border-bottom: none;
        }
        .balance-due {
            font-size: 16px;
            font-weight: bold;
            color: #2e7d32;
            margin-top: 10px;
        }
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            color: #666;
            text-align: center;
        }
        .page-break {
            page-break-before: always;
        }
        .payment-page {
            padding-top: 40px;
        }
        .payment-page h1 {
            font-size: 24px;
            margin: 0 0 30px 0;
            color: #000;
            text-align: center;
        }
        .payment-page .amount-due {
            text-align: center;
            margin-bottom: 40px;
        }
        .payment-page .amount-due .label {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        .payment-page .amount-due .amount {
            font-size: 32px;
            font-weight: bold;
            color: #000;
        }
        .payment-page .bank-details {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .payment-page .bank-details h2 {
            font-size: 14px;
            font-weight: bold;
            color: #666;
            text-transform: uppercase;
            margin: 0 0 15px 0;
            letter-spacing: 1px;
        }
        .payment-page .bank-details .detail-row {
            margin-bottom: 12px;
        }
        .payment-page .bank-details .detail-label {
            font-size: 11px;
            color: #666;
            margin-bottom: 2px;
        }
        .payment-page .bank-details .detail-value {
            font-size: 14px;
            font-weight: bold;
            color: #000;
            font-family: 'Courier New', monospace;
        }
        .payment-page .notice {
            background-color: #fff8e1;
            border: 1px solid #ffc107;
            border-radius: 5px;
            padding: 15px;
            font-size: 11px;
            color: #856404;
        }
        .payment-page .reference {
            margin-top: 30px;
            text-align: center;
        }
        .payment-page .reference .label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        .payment-page .reference .value {
            font-size: 16px;
            font-weight: bold;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-grid">
            <div class="header-left">
                <h1>{{ $invoice->isCreditNote() ? 'NOTA DE CRÉDITO' : 'FACTURA' }}</h1>
                <p><strong>Nº:</strong> {{ $invoice->invoice_number }}</p>
                <p><strong>Fecha:</strong> {{ $invoice->invoice_date->format('d/m/Y') }}</p>
                <p><strong>Vencimiento:</strong> {{ $invoice->due_date ? $invoice->due_date->format('d/m/Y') : 'Al Recibo' }}</p>
                @if($invoice->isCreditNote() && $invoice->parentInvoice)
                <p><strong>Referencia Factura Original:</strong> {{ $invoice->parentInvoice->invoice_number }}</p>
                @endif
            </div>
            <div class="header-right">
                <div class="from-details">
                    <strong>{{ $invoice->person->name }}</strong><br>
                    {{ $invoice->person->address }}<br>
                    {{ $invoice->person->postal_code }}, {{ $invoice->person->city }}<br>
                    {{ $invoice->person->country }}<br>
                    DNI/NIE: {{ $invoice->person->dni_nie }}
                </div>
            </div>
        </div>
    </div>

    @php
        $displayCustomerName = $invoice->customer_name ?: $invoice->customer?->name;
        $displayCustomerAddress = $invoice->customer_address ?: $invoice->customer?->address;
    @endphp
    <div class="section">
        <div class="section-title">DATOS DEL CLIENTE</div>
        <div class="details">
            <strong>{{ $displayCustomerName }}</strong><br>
            @if($displayCustomerAddress)
                {{ $displayCustomerAddress }}<br>
            @endif
            @if($invoice->customer_tax_id)
                <strong>NIF/CIF:</strong> {{ $invoice->customer_tax_id }}<br>
            @endif
        </div>
    </div>

    <div class="section">
        <div class="section-title">{{ $invoice->isCreditNote() ? 'DETALLES DE LA NOTA DE CRÉDITO' : 'DETALLES DE LA FACTURA' }}</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 40%;">Descripción</th>
                    <th class="text-right" style="width: 12%;">Cantidad</th>
                    <th style="width: 12%;">Unidad</th>
                    <th class="text-right" style="width: 16%;">Precio Unit.</th>
                    <th class="text-right" style="width: 20%;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="text-right">{{ rtrim(rtrim(number_format($item->quantity, 4, ',', '.'), '0'), ',') }}</td>
                    <td>{{ $item->unit?->labelEs() ?? 'Unidades' }}</td>
                    <td class="text-right">{{ number_format($item->unit_price / 100, 2, ',', '.') }} {{ $invoice->currency }}</td>
                    <td class="text-right">{{ number_format($item->total / 100, 2, ',', '.') }} {{ $invoice->currency }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="totals">
        <table>
            <tr class="total-row">
                <td><strong>TOTAL:</strong></td>
                <td class="text-right">
                    <strong>{{ number_format($invoice->total_amount / 100, 2, ',', '.') }} {{ $invoice->currency }}</strong>
                </td>
            </tr>
        </table>
    </div>

    @if($invoice->items->whereNotNull('stripe_transaction_id')->isNotEmpty())
    <div class="payment-info">
        <div class="paid-stamp">PAGADO / PAID</div>
        <table>
            <tr>
                <td><strong>Fecha de Pago:</strong></td>
                <td>{{ $invoice->invoice_date->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td><strong>Método de Pago:</strong></td>
                <td>Stripe</td>
            </tr>
        </table>
        <div class="balance-due">
            Saldo Pendiente: 0,00 {{ $invoice->currency }}
        </div>
    </div>
    @endif

    <div class="footer">
        <p>{{ $invoice->isCreditNote() ? 'Nota de crédito generada' : 'Factura generada' }} el {{ $invoice->generated_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }}</p>
    </div>

    @if($invoice->bankAccount && !$invoice->isCreditNote())
    <div class="page-break"></div>
    <div class="payment-page">
        <h1>DATOS DE PAGO</h1>

        <div class="amount-due">
            <div class="label">Importe a Pagar</div>
            <div class="amount">
                @php
                    $symbol = match($invoice->currency) {
                        'USD' => '$',
                        'GBP' => '£',
                        default => '€',
                    };
                @endphp
                {{ $symbol }}{{ number_format($invoice->total_amount / 100, 2, ',', '.') }} {{ $invoice->currency }}
            </div>
            @if($invoice->due_date)
                <div class="label" style="margin-top: 10px;">Vencimiento: {{ $invoice->due_date->format('d/m/Y') }}</div>
            @else
                <div class="label" style="margin-top: 10px;">Al recibo</div>
            @endif
        </div>

        <div class="bank-details">
            <h2>Beneficiario</h2>
            <div class="detail-row">
                <div class="detail-value">{{ $invoice->person->name }}</div>
            </div>
        </div>

        <div class="bank-details">
            <h2>Datos Bancarios</h2>

            @if($invoice->bankAccount->bank_name)
                <div class="detail-row">
                    <div class="detail-label">Banco</div>
                    <div class="detail-value">{{ $invoice->bankAccount->bank_name }}</div>
                </div>
            @endif

            @if($invoice->bankAccount->iban)
                <div class="detail-row">
                    <div class="detail-label">IBAN</div>
                    <div class="detail-value">{{ $invoice->bankAccount->iban }}</div>
                </div>
            @endif

            @if($invoice->bankAccount->swift_bic)
                <div class="detail-row">
                    <div class="detail-label">SWIFT/BIC</div>
                    <div class="detail-value">{{ $invoice->bankAccount->swift_bic }}</div>
                </div>
            @endif

            @if($invoice->bankAccount->account_number)
                <div class="detail-row">
                    <div class="detail-label">Número de Cuenta</div>
                    <div class="detail-value">{{ $invoice->bankAccount->account_number }}</div>
                </div>
            @endif

            @if($invoice->bankAccount->sort_code)
                <div class="detail-row">
                    <div class="detail-label">Código de clasificación/número de ruta</div>
                    <div class="detail-value">{{ $invoice->bankAccount->sort_code }}</div>
                </div>
            @endif

            <div class="detail-row">
                <div class="detail-label">Tipo de Cuenta</div>
                <div class="detail-value">Corriente (Checking)</div>
            </div>
        </div>

        <div class="reference">
            <div class="label">Referencia de Pago</div>
            <div class="value">{{ $invoice->invoice_number }}</div>
            <div class="label" style="margin-top: 5px;">Por favor, utilice esta referencia al realizar el pago</div>
        </div>

        <div class="notice" style="margin-top: 30px;">
            <strong>Importante:</strong> Por favor, pague el importe exacto indicado. Los gastos bancarios o de transferencia deben ser asumidos por el ordenante.
        </div>
    </div>
    @endif
</body>
</html>
