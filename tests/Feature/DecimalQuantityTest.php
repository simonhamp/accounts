<?php

use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;

it('can create invoice item with decimal quantity', function () {
    $invoice = Invoice::factory()->create();

    $item = InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'quantity' => 2.5,
        'unit_price' => 1000,
        'total' => 2500,
    ]);

    expect($item->quantity)->toBe('2.5000');
    expect($item->total)->toBe(2500);
});

it('can create bill item with decimal quantity', function () {
    $bill = Bill::factory()->create();

    $item = BillItem::factory()->create([
        'bill_id' => $bill->id,
        'quantity' => 1.75,
        'unit_price' => 2000,
        'total' => 3500,
    ]);

    expect($item->quantity)->toBe('1.7500');
    expect($item->total)->toBe(3500);
});

it('correctly calculates total with decimal quantity', function () {
    $invoice = Invoice::factory()->create();

    $quantity = 0.5;
    $unitPrice = 10000;
    $expectedTotal = (int) round($quantity * $unitPrice);

    $item = InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
        'total' => $expectedTotal,
    ]);

    expect($item->total)->toBe(5000);
});

it('handles small decimal quantities correctly', function () {
    $bill = Bill::factory()->create();

    $item = BillItem::factory()->create([
        'bill_id' => $bill->id,
        'quantity' => 0.0001,
        'unit_price' => 1000000,
        'total' => 100,
    ]);

    expect($item->quantity)->toBe('0.0001');
    expect($item->total)->toBe(100);
});

it('handles large decimal quantities correctly', function () {
    $invoice = Invoice::factory()->create();

    $item = InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'quantity' => 999.9999,
        'unit_price' => 100,
        'total' => 100000,
    ]);

    expect($item->quantity)->toBe('999.9999');
});
