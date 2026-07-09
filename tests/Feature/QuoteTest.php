<?php

use App\Enums\InvoiceStatus;
use App\Enums\QuoteStatus;
use App\Enums\TaxType;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\RelationManagers\QuotesRelationManager;
use App\Filament\Resources\People\Pages\EditPerson;
use App\Filament\Resources\People\RelationManagers\QuotesRelationManager as PersonQuotesRelationManager;
use App\Models\Customer;
use App\Models\Person;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\User;
use App\Services\QuoteService;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('allocates sequential quote numbers independent of invoice numbers', function () {
    $person = Person::factory()->create([
        'invoice_prefix' => 'SH',
        'next_invoice_number' => 5,
        'next_quote_number' => 1,
    ]);

    expect($person->getNextQuoteNumber())->toBe('SH-Q-00001');

    $first = DB::transaction(fn () => $person->allocateNextQuoteNumber());
    $second = DB::transaction(fn () => $person->allocateNextQuoteNumber());

    expect($first)->toBe('SH-Q-00001');
    expect($second)->toBe('SH-Q-00002');
    expect($person->fresh()->next_invoice_number)->toBe(5);
    expect($person->fresh()->next_quote_number)->toBe(3);
});

it('calculates totals from line items including tax and IRPF', function () {
    $quote = Quote::factory()->create([
        'currency' => 'EUR',
        'irpf_rate' => 15,
    ]);

    QuoteItem::factory()->for($quote)->create([
        'quantity' => 2,
        'unit_price' => 10000,
        'total' => 20000,
        'tax_type' => TaxType::Iva,
        'tax_rate' => 21,
    ]);

    $quote->recalculateTotal();
    $quote->refresh();

    expect($quote->tax_base_total)->toBe(20000);
    expect($quote->tax_total)->toBe(4200);
    expect($quote->irpf_amount)->toBe(3000);
    expect($quote->total_amount)->toBe(21200);
});

it('transitions through the accept flow', function () {
    $quote = Quote::factory()->create();

    expect($quote->status)->toBe(QuoteStatus::Draft);
    expect($quote->canBeSent())->toBeTrue();
    expect($quote->canBeAccepted())->toBeFalse();
    expect($quote->canBeRejected())->toBeFalse();
    expect($quote->canCreateInvoice())->toBeFalse();

    $quote->markAsSent();
    expect($quote->fresh()->status)->toBe(QuoteStatus::Sent);
    expect($quote->fresh()->canBeAccepted())->toBeTrue();

    $quote->markAsAccepted();
    $quote->refresh();
    expect($quote->status)->toBe(QuoteStatus::Accepted);
    expect($quote->canBeAccepted())->toBeFalse();
    expect($quote->canBeRejected())->toBeTrue();
    expect($quote->canCreateInvoice())->toBeTrue();
});

it('allows an accepted quote to be rejected before it is invoiced', function () {
    $quote = Quote::factory()->accepted()->create();

    $quote->markAsRejected();
    $quote->refresh();

    expect($quote->status)->toBe(QuoteStatus::Rejected);
    expect($quote->canCreateInvoice())->toBeFalse();

    $invoiced = Quote::factory()->invoiced()->create();
    expect($invoiced->canBeRejected())->toBeFalse();
});

it('transitions through the reject flow', function () {
    $quote = Quote::factory()->sent()->create();

    $quote->markAsRejected();
    $quote->refresh();

    expect($quote->status)->toBe(QuoteStatus::Rejected);
    expect($quote->canCreateInvoice())->toBeFalse();
    expect($quote->canBeAccepted())->toBeFalse();
});

it('creates an invoice from an accepted quote', function () {
    $person = Person::factory()->create([
        'invoice_prefix' => 'SH',
        'next_invoice_number' => 1,
        'next_quote_number' => 1,
    ]);

    $quote = Quote::factory()->accepted()->for($person)->create([
        'quote_number' => 'SH-Q-00001',
        'customer_name' => 'Acme Corp',
        'customer_address' => '1 Acme Way',
        'customer_tax_id' => 'B12345678',
        'currency' => 'EUR',
        'irpf_rate' => 15,
        'legal_notes' => 'Some legal note',
    ]);

    QuoteItem::factory()->for($quote)->create([
        'description' => 'Consulting',
        'quantity' => 2,
        'unit_price' => 10000,
        'total' => 20000,
        'tax_type' => TaxType::Iva,
        'tax_rate' => 21,
    ]);

    $invoice = app(QuoteService::class)->convertToInvoice($quote->fresh());

    expect($invoice->invoice_number)->toBe('SH-00001');
    expect($invoice->status)->toBe(InvoiceStatus::Reviewed);
    expect($invoice->customer_name)->toBe('Acme Corp');
    expect($invoice->customer_address)->toBe('1 Acme Way');
    expect($invoice->customer_tax_id)->toBe('B12345678');
    expect((float) $invoice->irpf_rate)->toBe(15.0);
    expect($invoice->legal_notes)->toBe('Some legal note');
    expect($invoice->items)->toHaveCount(1);

    $item = $invoice->items->first();
    expect($item->description)->toBe('Consulting');
    expect($item->total)->toBe(20000);
    expect($item->tax_type)->toBe(TaxType::Iva);

    $invoice->refresh();
    expect($invoice->tax_base_total)->toBe(20000);
    expect($invoice->total_amount)->toBe(21200);

    $quote->refresh();
    expect($quote->status)->toBe(QuoteStatus::Invoiced);
    expect($quote->invoice_id)->toBe($invoice->id);
    expect($invoice->quote?->id)->toBe($quote->id);

    expect($person->fresh()->next_invoice_number)->toBe(2);
});

it('refuses to create an invoice from a quote that is not accepted', function () {
    $quote = Quote::factory()->sent()->create();

    app(QuoteService::class)->convertToInvoice($quote);
})->throws(Exception::class, 'Only accepted quotes can be converted to an invoice.');

it('duplicates a quote as a fresh draft with copied line items', function () {
    $person = Person::factory()->create([
        'invoice_prefix' => 'SH',
        'next_quote_number' => 2,
    ]);

    $quote = Quote::factory()->rejected()->for($person)->create([
        'quote_number' => 'SH-Q-00001',
        'customer_name' => 'Acme Corp',
        'pdf_path' => 'quotes/SH/2026/SH-Q-00001.pdf',
        'pdf_path_en' => 'quotes/SH/2026/SH-Q-00001-en.pdf',
        'generated_at' => now(),
    ]);

    QuoteItem::factory()->for($quote)->count(2)->create([
        'quantity' => 1,
        'unit_price' => 5000,
        'total' => 5000,
    ]);

    $copy = app(QuoteService::class)->duplicate($quote->fresh());

    expect($copy->id)->not->toBe($quote->id);
    expect($copy->quote_number)->toBe('SH-Q-00002');
    expect($copy->status)->toBe(QuoteStatus::Draft);
    expect($copy->customer_name)->toBe('Acme Corp');
    expect($copy->quote_date->isToday())->toBeTrue();
    expect($copy->pdf_path)->toBeNull();
    expect($copy->pdf_path_en)->toBeNull();
    expect($copy->generated_at)->toBeNull();
    expect($copy->invoice_id)->toBeNull();
    expect($copy->items)->toHaveCount(2);
    expect($copy->total_amount)->toBe(10000);

    expect($quote->fresh()->status)->toBe(QuoteStatus::Rejected);
    expect($quote->fresh()->items)->toHaveCount(2);
});

it('generates Spanish and English PDFs for a quote', function () {
    Storage::fake();

    $person = Person::factory()->create(['invoice_prefix' => 'SH']);
    $quote = Quote::factory()->for($person)->create([
        'quote_number' => 'SH-Q-00001',
        'quote_date' => '2026-07-09',
    ]);

    QuoteItem::factory()->for($quote)->create();

    app(QuoteService::class)->generateAndStorePdf($quote);
    $quote->refresh();

    expect($quote->pdf_path)->toBe('quotes/SH/2026/SH-Q-00001.pdf');
    expect($quote->pdf_path_en)->toBe('quotes/SH/2026/SH-Q-00001-en.pdf');
    expect($quote->generated_at)->not->toBeNull();

    Storage::assertExists($quote->pdf_path);
    Storage::assertExists($quote->pdf_path_en);
});

it('downloads quote PDFs in both languages', function () {
    Storage::fake();

    $quote = Quote::factory()->create([
        'pdf_path' => 'quotes/SH/2026/SH-Q-00001.pdf',
        'pdf_path_en' => 'quotes/SH/2026/SH-Q-00001-en.pdf',
    ]);

    Storage::put($quote->pdf_path, 'spanish pdf');
    Storage::put($quote->pdf_path_en, 'english pdf');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('quotes.download-pdf', ['quote' => $quote, 'language' => 'es']))
        ->assertSuccessful()
        ->assertDownload($quote->quote_number.'.pdf');

    $this->actingAs($user)
        ->get(route('quotes.download-pdf', ['quote' => $quote, 'language' => 'en']))
        ->assertSuccessful()
        ->assertDownload($quote->quote_number.'-en.pdf');
});

it('returns 404 when downloading a quote without a generated PDF', function () {
    $quote = Quote::factory()->create(['pdf_path' => null]);

    $this->actingAs(User::factory()->create())
        ->get(route('quotes.download-pdf', ['quote' => $quote]))
        ->assertNotFound();
});

it('lists a customer\'s quotes in the relation manager on the customer page', function () {
    $admin = User::factory()->admin()->create();
    $customer = Customer::factory()->create();
    $quote = Quote::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($admin)
        ->get("/admin/customers/{$customer->id}/edit")
        ->assertSuccessful();

    Livewire::actingAs($admin)
        ->test(QuotesRelationManager::class, [
            'ownerRecord' => $customer,
            'pageClass' => EditCustomer::class,
        ])
        ->assertSuccessful()
        ->assertSee($quote->quote_number);
});

it('lists a person\'s quotes in the relation manager on the person page', function () {
    $admin = User::factory()->admin()->create();
    $person = Person::factory()->create();
    $quote = Quote::factory()->for($person)->create();

    Livewire::actingAs($admin)
        ->test(PersonQuotesRelationManager::class, [
            'ownerRecord' => $person,
            'pageClass' => EditPerson::class,
        ])
        ->assertSuccessful()
        ->assertSee($quote->quote_number);
});

it('renders the quote admin pages', function () {
    $admin = User::factory()->admin()->create();
    $quote = Quote::factory()->accepted()->create();

    $this->actingAs($admin)
        ->get('/admin/quotes')
        ->assertSuccessful();

    $this->actingAs($admin)
        ->get('/admin/quotes/create')
        ->assertSuccessful();

    $this->actingAs($admin)
        ->get("/admin/quotes/{$quote->id}/edit")
        ->assertSuccessful();
});
