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
        .header h1 {
            font-size: 24px;
            margin: 0 0 10px 0;
            color: #000;
        }
        .header-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
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
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $invoice->isCreditNote() ? 'NOTA DE CRÉDITO' : 'FACTURA' }}</h1>
        <p><strong>Nº:</strong> {{ $invoice->invoice_number }}</p>
        <p><strong>Fecha:</strong> {{ $invoice->invoice_date->format('d/m/Y') }}</p>
        <p><strong>Vencimiento:</strong> {{ $invoice->due_date ? $invoice->due_date->format('d/m/Y') : 'Al Recibo' }}</p>
        @if($invoice->isCreditNote() && $invoice->parentInvoice)
        <p><strong>Referencia Factura Original:</strong> {{ $invoice->parentInvoice->invoice_number }}</p>
        @endif
    </div>

    <div class="section">
        <div class="section-title">DATOS DEL EMISOR</div>
        <div class="details">
            <strong>{{ $invoice->person->name }}</strong><br>
            {{ $invoice->person->address }}<br>
            {{ $invoice->person->postal_code }}, {{ $invoice->person->city }}<br>
            {{ $invoice->person->country }}<br>
            <strong>DNI/NIE:</strong> {{ $invoice->person->dni_nie }}
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
</body>
</html>
