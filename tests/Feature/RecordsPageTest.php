<?php

use App\Models\Bill;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Person;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('groups records by calendar month for a Sociedad Limitada', function () {
    $person = Person::factory()->sociedadLimitada()->canarias()->create([
        'invoice_prefix' => 'SLM',
    ]);

    Invoice::factory()->paid()->create([
        'person_id' => $person->id,
        'invoice_number' => 'SLM-00001',
        'invoice_date' => '2026-02-12',
        'total_amount' => 10000,
        'amount_eur' => 10000,
        'currency' => 'EUR',
    ]);

    Invoice::factory()->paid()->create([
        'person_id' => $person->id,
        'invoice_number' => 'SLM-00002',
        'invoice_date' => '2026-04-18',
        'total_amount' => 25000,
        'amount_eur' => 25000,
        'currency' => 'EUR',
    ]);

    $response = $this->get(route('records.index', ['person' => $person->id, 'year' => 2026]));

    $response->assertOk()
        ->assertSee('February')
        ->assertSee('April')
        ->assertDontSee('February 2026')
        ->assertDontSee('April 2026')
        ->assertSee('Month Subtotal (EUR)')
        ->assertSee('SLM-00001')
        ->assertSee('SLM-00002');
});

it('keeps a single un-grouped table for an individual person', function () {
    $person = Person::factory()->create([
        'invoice_prefix' => 'IND',
    ]);

    Invoice::factory()->paid()->create([
        'person_id' => $person->id,
        'invoice_number' => 'IND-00001',
        'invoice_date' => '2026-02-12',
        'total_amount' => 10000,
        'amount_eur' => 10000,
        'currency' => 'EUR',
    ]);

    Invoice::factory()->paid()->create([
        'person_id' => $person->id,
        'invoice_number' => 'IND-00002',
        'invoice_date' => '2026-04-18',
        'total_amount' => 25000,
        'amount_eur' => 25000,
        'currency' => 'EUR',
    ]);

    $response = $this->get(route('records.index', ['person' => $person->id, 'year' => 2026]));

    $response->assertOk()
        ->assertDontSee('Month Subtotal (EUR)')
        ->assertSee('Total (EUR)')
        ->assertSee('IND-00001')
        ->assertSee('IND-00002');
});

it('renders month tabs and a month-scoped download link for a legal entity', function () {
    $person = Person::factory()->sociedadLimitada()->canarias()->create([
        'invoice_prefix' => 'SLT',
    ]);

    Invoice::factory()->paid()->create([
        'person_id' => $person->id,
        'invoice_number' => 'SLT-00001',
        'invoice_date' => '2026-02-12',
        'total_amount' => 10000,
        'amount_eur' => 10000,
        'currency' => 'EUR',
    ]);
    Invoice::factory()->paid()->create([
        'person_id' => $person->id,
        'invoice_number' => 'SLT-00002',
        'invoice_date' => '2026-04-18',
        'total_amount' => 25000,
        'amount_eur' => 25000,
        'currency' => 'EUR',
    ]);

    $response = $this->get(route('records.index', ['person' => $person->id, 'year' => 2026]));

    $response->assertOk()
        ->assertSee("activeMonth: '2026-04'", false)
        ->assertSee("activeMonth = '2026-02'", false)
        ->assertSee("activeMonth = '2026-04'", false)
        ->assertSee('Download Month');
});

it('downloads only files from the requested month when month is set', function () {
    Storage::fake('local');
    Storage::disk('local')->put('bills/feb.pdf', 'feb');
    Storage::disk('local')->put('bills/apr.pdf', 'apr');
    Storage::disk('local')->put('documents/yearly.pdf', 'yearly');

    $person = Person::factory()->sociedadLimitada()->canarias()->create([
        'invoice_prefix' => 'SLD',
    ]);
    $supplier = Supplier::factory()->create();

    Bill::factory()->paid()->create([
        'person_id' => $person->id,
        'supplier_id' => $supplier->id,
        'bill_number' => 'B-FEB',
        'bill_date' => '2026-02-10',
        'total_amount' => 5000,
        'amount_eur' => 5000,
        'currency' => 'EUR',
        'original_file_path' => 'bills/feb.pdf',
    ]);
    Bill::factory()->paid()->create([
        'person_id' => $person->id,
        'supplier_id' => $supplier->id,
        'bill_number' => 'B-APR',
        'bill_date' => '2026-04-10',
        'total_amount' => 7000,
        'amount_eur' => 7000,
        'currency' => 'EUR',
        'original_file_path' => 'bills/apr.pdf',
    ]);
    Document::factory()->create([
        'year' => 2026,
        'file_path' => 'documents/yearly.pdf',
        'original_filename' => 'yearly.pdf',
    ]);

    $response = $this->get(route('records.download', ['person' => $person->id, 'year' => 2026]).'?month=2026-04');

    $response->assertOk();

    $tempDir = sys_get_temp_dir();
    $tempFile = tempnam($tempDir, 'zip');
    file_put_contents($tempFile, $response->streamedContent());

    $zip = new ZipArchive;
    expect($zip->open($tempFile))->toBeTrue();

    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = $zip->getNameIndex($i);
    }
    $zip->close();
    @unlink($tempFile);

    $billEntries = array_filter($names, fn ($n) => str_starts_with($n, 'bills/') && ! str_ends_with($n, '/'));
    $documentEntries = array_filter($names, fn ($n) => str_starts_with($n, 'documents/') && ! str_ends_with($n, '/'));

    expect(count($billEntries))->toBe(1);
    expect(implode('|', $billEntries))->toContain('2026-04-10');
    expect(implode('|', $billEntries))->not->toContain('2026-02-10');
    expect($documentEntries)->toBeEmpty();
});
