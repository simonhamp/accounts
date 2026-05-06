<?php

use App\Models\Document;
use App\Models\Invoice;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

describe('Document Model', function () {
    it('can create a document', function () {
        $document = Document::factory()->create([
            'original_filename' => 'test-document.pdf',
            'description' => 'Test description',
            'year' => 2025,
        ]);

        expect($document->original_filename)->toBe('test-document.pdf');
        expect($document->description)->toBe('Test description');
        expect($document->year)->toBe(2025);
    });

    it('can filter documents by year', function () {
        Document::factory()->create(['year' => 2024]);
        Document::factory()->create(['year' => 2025]);
        Document::factory()->create(['year' => 2025]);

        expect(Document::forYear(2025)->count())->toBe(2);
        expect(Document::forYear(2024)->count())->toBe(1);
    });

    it('casts year as integer', function () {
        $document = Document::factory()->create(['year' => '2025']);

        expect($document->year)->toBeInt();
        expect($document->year)->toBe(2025);
    });
});

describe('Document Download', function () {
    it('downloads a document file', function () {
        Storage::fake('local');
        Storage::disk('local')->put('documents/test.pdf', 'PDF content');

        $document = Document::factory()->create([
            'file_path' => 'documents/test.pdf',
            'original_filename' => 'test-document.pdf',
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('documents.download', $document))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    });

    it('returns 404 if document file not found', function () {
        Storage::fake('local');

        $document = Document::factory()->create([
            'file_path' => 'documents/nonexistent.pdf',
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('documents.download', $document))
            ->assertNotFound();
    });

    it('returns 404 if document has empty file path', function () {
        $document = Document::factory()->create([
            'file_path' => '',
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('documents.download', $document))
            ->assertNotFound();
    });
});

describe('Records Page Documents', function () {
    it('shows documents in records page', function () {
        $person = Person::factory()->create();
        $user = User::factory()->create();

        // Create an invoice to make the year show up in the tabs
        Invoice::factory()->create([
            'person_id' => $person->id,
            'invoice_date' => '2025-06-15',
        ]);

        Document::factory()->create([
            'original_filename' => 'tax-summary.pdf',
            'description' => 'Tax Summary 2025',
            'year' => 2025,
        ]);

        $this->actingAs($user)
            ->get(route('records.index', ['person' => $person->id, 'year' => 2025]))
            ->assertOk()
            ->assertSee('tax-summary.pdf')
            ->assertSee('Tax Summary 2025');
    });

    it('filters documents by year in records page', function () {
        $person = Person::factory()->create();
        $user = User::factory()->create();

        // Create invoices to make the years show up in the tabs
        Invoice::factory()->create([
            'person_id' => $person->id,
            'invoice_date' => '2024-06-15',
        ]);
        Invoice::factory()->create([
            'person_id' => $person->id,
            'invoice_date' => '2025-06-15',
        ]);

        Document::factory()->create([
            'original_filename' => 'doc-2024.pdf',
            'year' => 2024,
        ]);

        Document::factory()->create([
            'original_filename' => 'doc-2025.pdf',
            'year' => 2025,
        ]);

        $this->actingAs($user)
            ->get(route('records.index', ['person' => $person->id, 'year' => 2025]))
            ->assertOk()
            ->assertSee('doc-2025.pdf')
            ->assertDontSee('doc-2024.pdf');
    });
});
