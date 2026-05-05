<?php

use App\Enums\CustomerTaxRegion;
use App\Enums\EntityType;
use App\Enums\TaxRegime;
use App\Enums\TaxType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Person;

it('stores Spanish legal entity fields on a Person', function () {
    $person = Person::factory()->sociedadLimitada()->canarias()->create([
        'name' => 'Sinoperro',
    ]);

    expect($person->entity_type)->toBe(EntityType::SociedadLimitada);
    expect($person->tax_regime)->toBe(TaxRegime::Canarias);
    expect($person->isLegalEntity())->toBeTrue();
    expect($person->cif)->not->toBeNull();
    expect($person->dni_nie)->toBeNull();
    expect($person->taxIdentifier())->toBe($person->cif);
    expect($person->taxIdentifierLabel())->toBe('CIF');
    expect($person->registro_mercantil)->toContain('Registro Mercantil');
});

it('falls back to DNI/NIE for individuals', function () {
    $person = Person::factory()->create();

    expect($person->isLegalEntity())->toBeFalse();
    expect($person->taxIdentifierLabel())->toBe('DNI/NIE');
    expect($person->taxIdentifier())->toBe($person->dni_nie);
});

it('calculates tax_amount on InvoiceItem when tax_rate is set', function () {
    $invoice = Invoice::factory()->create([
        'total_amount' => 0,
    ]);

    $item = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'description' => 'Service',
        'quantity' => 1,
        'unit_price' => 10000,
        'total' => 10000,
        'tax_type' => TaxType::Igic,
        'tax_rate' => 7,
    ]);

    expect($item->tax_amount)->toBe(700);
});

it('rolls totals up to the Invoice including tax and IRPF', function () {
    $invoice = Invoice::factory()->create([
        'total_amount' => 0,
        'irpf_rate' => 15,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'description' => 'Consulting',
        'quantity' => 1,
        'unit_price' => 100000,
        'total' => 100000,
        'tax_type' => TaxType::Iva,
        'tax_rate' => 21,
    ]);

    $invoice->save();
    $invoice->refresh();

    expect($invoice->tax_base_total)->toBe(100000);
    expect($invoice->tax_total)->toBe(21000);
    expect($invoice->irpf_amount)->toBe(15000);
    expect($invoice->total_amount)->toBe(106000);
});

it('preserves existing behaviour when no tax is set on items', function () {
    $invoice = Invoice::factory()->create([
        'total_amount' => 0,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'description' => 'Legacy item',
        'quantity' => 2,
        'unit_price' => 5000,
        'total' => 10000,
    ]);

    $invoice->save();
    $invoice->refresh();

    expect($invoice->tax_base_total)->toBe(10000);
    expect($invoice->tax_total)->toBe(0);
    expect($invoice->irpf_amount)->toBe(0);
    expect($invoice->total_amount)->toBe(10000);
    expect($invoice->hasTaxBreakdown())->toBeFalse();
});

it('groups items into a tax breakdown by type and rate', function () {
    $invoice = Invoice::factory()->create(['total_amount' => 0]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'description' => 'A',
        'quantity' => 1,
        'unit_price' => 10000,
        'total' => 10000,
        'tax_type' => TaxType::Igic,
        'tax_rate' => 7,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'description' => 'B',
        'quantity' => 1,
        'unit_price' => 20000,
        'total' => 20000,
        'tax_type' => TaxType::Igic,
        'tax_rate' => 7,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'description' => 'C',
        'quantity' => 1,
        'unit_price' => 5000,
        'total' => 5000,
        'tax_type' => TaxType::Igic,
        'tax_rate' => 3,
    ]);

    $invoice->load('items');
    $breakdown = $invoice->taxBreakdown();

    expect($breakdown)->toHaveCount(2);

    $sevenPct = $breakdown->firstWhere('rate', 7.0);
    $threePct = $breakdown->firstWhere('rate', 3.0);

    expect($sevenPct['base'])->toBe(30000);
    expect($sevenPct['tax'])->toBe(2100);
    expect($threePct['base'])->toBe(5000);
    expect($threePct['tax'])->toBe(150);
});

it('renders CIF, Registro Mercantil and IGIC on a Spanish PDF for a legal entity', function () {
    $person = Person::factory()->sociedadLimitada()->canarias()->create([
        'name' => 'Sinoperro',
        'cif' => 'B12345678',
        'registro_mercantil' => 'Inscrita en el Registro Mercantil de Las Palmas, Tomo 1, Folio 1, Hoja 1',
        'share_capital' => 300000,
    ]);

    $invoice = Invoice::factory()->create([
        'person_id' => $person->id,
        'invoice_number' => 'SP-00001',
        'total_amount' => 0,
        'currency' => 'EUR',
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'description' => 'Servicios de desarrollo',
        'quantity' => 1,
        'unit_price' => 100000,
        'total' => 100000,
        'tax_type' => TaxType::Igic,
        'tax_rate' => 7,
    ]);

    $invoice->save();
    $invoice->load(['person', 'items', 'bankAccount']);

    $html = view('invoices.pdf-es', ['invoice' => $invoice])->render();

    expect($html)->toContain('Sinoperro, S.L.');
    expect($html)->toContain('CIF: B12345678');
    expect($html)->toContain('Registro Mercantil de Las Palmas');
    expect($html)->toContain('Capital Social: 3.000,00 EUR');
    expect($html)->toContain('IGIC 7%');
    expect($html)->toContain('Base Imponible');
});

it('requires IGIC reverse-charge note for Canarias issuer invoicing peninsula customer', function () {
    $person = Person::factory()->sociedadLimitada()->canarias()->create();
    $customer = Customer::factory()->peninsulaSpain()->create();

    $invoice = Invoice::factory()->create([
        'person_id' => $person->id,
        'customer_id' => $customer->id,
    ]);

    $invoice->load(['person', 'customer']);

    expect($invoice->requiresIgicReverseChargeNote())->toBeTrue();
});

it('does not require IGIC reverse-charge note when customer is also in Canarias', function () {
    $person = Person::factory()->sociedadLimitada()->canarias()->create();
    $customer = Customer::factory()->canarias()->create();

    $invoice = Invoice::factory()->create([
        'person_id' => $person->id,
        'customer_id' => $customer->id,
    ]);

    $invoice->load(['person', 'customer']);

    expect($invoice->requiresIgicReverseChargeNote())->toBeFalse();
});

it('does not require IGIC reverse-charge note when issuer is not Canarias', function () {
    $person = Person::factory()->create(['tax_regime' => TaxRegime::PeninsulaBaleares]);
    $customer = Customer::factory()->create(['tax_region' => CustomerTaxRegion::EuropeanUnion]);

    $invoice = Invoice::factory()->create([
        'person_id' => $person->id,
        'customer_id' => $customer->id,
    ]);

    $invoice->load(['person', 'customer']);

    expect($invoice->requiresIgicReverseChargeNote())->toBeFalse();
});

it('renders the IGIC reverse-charge clause in both languages when applicable', function () {
    $person = Person::factory()->sociedadLimitada()->canarias()->create();
    $customer = Customer::factory()->create(['tax_region' => CustomerTaxRegion::EuropeanUnion]);

    $invoice = Invoice::factory()->create([
        'person_id' => $person->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'SP-00001',
        'total_amount' => 0,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'description' => 'Service',
        'quantity' => 1,
        'unit_price' => 100000,
        'total' => 100000,
    ]);

    $invoice->save();
    $invoice->load(['person', 'customer', 'items', 'bankAccount']);

    $htmlEs = view('invoices.pdf-es', ['invoice' => $invoice])->render();
    $htmlEn = view('invoices.pdf-en', ['invoice' => $invoice])->render();

    expect($htmlEs)->toContain('Operación no sujeta a IGIC. Inversión del sujeto pasivo.');
    expect($htmlEn)->toContain('Transaction not subject to IGIC. Reverse charge applies');
});

it('seeded the Sinoperro Person via migration', function () {
    $sinoperro = Person::where('cif', 'B26722488')->first();

    expect($sinoperro)->not->toBeNull();
    expect($sinoperro->name)->toBe('SINOPERRO, S.L.');
    expect($sinoperro->entity_type)->toBe(EntityType::SociedadLimitada);
    expect($sinoperro->tax_regime)->toBe(TaxRegime::Canarias);
    expect($sinoperro->city)->toBe('Las Palmas de Gran Canaria');
    expect($sinoperro->postal_code)->toBe('35010');
    expect($sinoperro->invoice_prefix)->toBe('SP');
    expect($sinoperro->is_default)->toBeTrue();
});

it('Person::default() returns the flagged person', function () {
    Person::query()->update(['is_default' => false]);

    $a = Person::factory()->create(['is_default' => false]);
    $b = Person::factory()->create(['is_default' => true]);
    Person::factory()->create(['is_default' => false]);

    expect(Person::default()?->id)->toBe($b->id);
});

it('Person::default() returns null when no default is flagged', function () {
    Person::query()->update(['is_default' => false]);

    Person::factory()->count(2)->create(['is_default' => false]);

    expect(Person::default())->toBeNull();
});

it('renders without tax breakdown for an individual with no tax on items', function () {
    $person = Person::factory()->create([
        'name' => 'Jane Doe',
        'dni_nie' => 'X1234567Y',
    ]);

    $invoice = Invoice::factory()->create([
        'person_id' => $person->id,
        'invoice_number' => 'JD-00001',
        'total_amount' => 0,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'description' => 'Legacy work',
        'quantity' => 1,
        'unit_price' => 50000,
        'total' => 50000,
    ]);

    $invoice->save();
    $invoice->load(['person', 'items', 'bankAccount']);

    $html = view('invoices.pdf-es', ['invoice' => $invoice])->render();

    expect($html)->toContain('DNI/NIE: X1234567Y');
    expect($html)->not->toContain('Base Imponible');
    expect($html)->not->toContain('Capital Social');
});
