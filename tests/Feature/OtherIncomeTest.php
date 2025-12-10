<?php

use App\Enums\OtherIncomeStatus;
use App\Models\BankAccount;
use App\Models\IncomeSource;
use App\Models\OtherIncome;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('IncomeSource Model', function () {
    it('creates an income source with factory', function () {
        $source = IncomeSource::factory()->create();

        expect($source->name)->not->toBeEmpty();
        expect($source->is_active)->toBeTrue();
    });

    it('can have multiple other incomes', function () {
        $source = IncomeSource::factory()->create();
        $person = Person::factory()->create();

        OtherIncome::factory()->count(3)->create([
            'income_source_id' => $source->id,
            'person_id' => $person->id,
        ]);

        expect($source->otherIncomes)->toHaveCount(3);
    });

    it('scopes active sources', function () {
        IncomeSource::factory()->create(['is_active' => true]);
        IncomeSource::factory()->create(['is_active' => false]);

        expect(IncomeSource::active()->count())->toBe(1);
    });
});

describe('OtherIncome Model', function () {
    it('creates other income with factory', function () {
        $income = OtherIncome::factory()->create();

        expect($income->description)->not->toBeEmpty();
        expect($income->amount)->toBeGreaterThan(0);
        expect($income->person)->not->toBeNull();
    });

    it('belongs to a person', function () {
        $person = Person::factory()->create();
        $income = OtherIncome::factory()->create(['person_id' => $person->id]);

        expect($income->person->id)->toBe($person->id);
    });

    it('belongs to an income source', function () {
        $source = IncomeSource::factory()->create();
        $income = OtherIncome::factory()->create(['income_source_id' => $source->id]);

        expect($income->incomeSource->id)->toBe($source->id);
    });

    it('can have a null income source', function () {
        $income = OtherIncome::factory()->create(['income_source_id' => null]);

        expect($income->incomeSource)->toBeNull();
    });

    it('knows if it has an original file', function () {
        $withFile = OtherIncome::factory()->create(['original_file_path' => 'documents/test.pdf']);
        $withoutFile = OtherIncome::factory()->create(['original_file_path' => null]);

        expect($withFile->hasOriginalFile())->toBeTrue();
        expect($withoutFile->hasOriginalFile())->toBeFalse();
    });

    it('knows if it is from CSV import', function () {
        $fromCsv = OtherIncome::factory()->create(['source_filename' => 'payouts.csv']);
        $notFromCsv = OtherIncome::factory()->create(['source_filename' => null]);

        expect($fromCsv->isFromCsv())->toBeTrue();
        expect($notFromCsv->isFromCsv())->toBeFalse();
    });

    it('casts income_date as date', function () {
        $income = OtherIncome::factory()->create(['income_date' => '2024-06-15']);

        expect($income->income_date)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        expect($income->income_date->format('Y-m-d'))->toBe('2024-06-15');
    });

    it('casts extracted_data as array', function () {
        $data = ['payer' => 'LemonSqueezy', 'payout_id' => 'payout_123'];
        $income = OtherIncome::factory()->create(['extracted_data' => $data]);

        expect($income->extracted_data)->toBe($data);
    });
});

describe('OtherIncome Filament Resource', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('can render the list page', function () {
        $response = $this->get('/admin/other-incomes');

        $response->assertSuccessful();
    });

    it('can render the create page', function () {
        $response = $this->get('/admin/other-incomes/create');

        $response->assertSuccessful();
    });

    it('can render the edit page', function () {
        $income = OtherIncome::factory()->create();

        $response = $this->get("/admin/other-incomes/{$income->id}/edit");

        $response->assertSuccessful();
    });
});

describe('IncomeSource Filament Resource', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('can render the list page', function () {
        $response = $this->get('/admin/income-sources');

        $response->assertSuccessful();
    });

    it('can render the create page', function () {
        $response = $this->get('/admin/income-sources/create');

        $response->assertSuccessful();
    });

    it('can render the edit page', function () {
        $source = IncomeSource::factory()->create();

        $response = $this->get("/admin/income-sources/{$source->id}/edit");

        $response->assertSuccessful();
    });
});

describe('Person OtherIncomes Relationship', function () {
    it('person can have multiple other incomes', function () {
        $person = Person::factory()->create();

        OtherIncome::factory()->count(5)->create(['person_id' => $person->id]);

        expect($person->otherIncomes)->toHaveCount(5);
    });
});

describe('Import Page', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('can render the import other income page', function () {
        $response = $this->get('/admin/import-other-income');

        $response->assertSuccessful();
    });
});

describe('OtherIncome PDF Preview', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('returns 404 when no PDF file path exists', function () {
        $income = OtherIncome::factory()->create(['original_file_path' => null]);

        $response = $this->get(route('other-incomes.original-pdf', $income));

        $response->assertNotFound();
    });

    it('returns 404 when PDF file does not exist on disk', function () {
        $income = OtherIncome::factory()->create(['original_file_path' => 'non-existent/file.pdf']);

        $response = $this->get(route('other-incomes.original-pdf', $income));

        $response->assertNotFound();
    });

    it('returns the PDF file when it exists', function () {
        $pdfPath = 'other-income-documents/test-income.pdf';
        \Illuminate\Support\Facades\Storage::disk('local')->put($pdfPath, '%PDF-1.4 test content');

        $income = OtherIncome::factory()->create(['original_file_path' => $pdfPath]);

        $response = $this->get(route('other-incomes.original-pdf', $income));

        $response->assertSuccessful();
        $response->assertHeader('Content-Type', 'application/pdf');

        \Illuminate\Support\Facades\Storage::disk('local')->delete($pdfPath);
    });
});

describe('OtherIncome Payment Status', function () {
    it('defaults to pending status', function () {
        $income = OtherIncome::factory()->create();

        expect($income->status)->toBe(OtherIncomeStatus::Pending);
        expect($income->isPending())->toBeTrue();
    });

    it('can be marked as paid', function () {
        $income = OtherIncome::factory()->create(['amount' => 10000]);

        $income->markAsPaid();

        expect($income->status)->toBe(OtherIncomeStatus::Paid);
        expect($income->amount_paid)->toBe(10000);
        expect($income->paid_at)->not->toBeNull();
        expect($income->isPaid())->toBeTrue();
    });

    it('can be marked as paid with different amount', function () {
        $income = OtherIncome::factory()->create(['amount' => 10000]);

        $income->markAsPaid(amountPaid: 9500);

        expect($income->status)->toBe(OtherIncomeStatus::Paid);
        expect($income->amount_paid)->toBe(9500);
        expect($income->isPartialPayment())->toBeTrue();
        expect($income->getOutstandingAmount())->toBe(500);
    });

    it('can be marked as paid with bank account', function () {
        $bankAccount = BankAccount::factory()->create();
        $income = OtherIncome::factory()->create(['amount' => 10000]);

        $income->markAsPaid(bankAccountId: $bankAccount->id);

        expect($income->bank_account_id)->toBe($bankAccount->id);
        expect($income->bankAccount->id)->toBe($bankAccount->id);
    });

    it('calculates outstanding amount correctly', function () {
        $income = OtherIncome::factory()->create(['amount' => 10000, 'amount_paid' => 7500]);

        expect($income->getOutstandingAmount())->toBe(2500);
    });

    it('returns zero outstanding when fully paid', function () {
        $income = OtherIncome::factory()->create(['amount' => 10000, 'amount_paid' => 10000]);

        expect($income->getOutstandingAmount())->toBe(0);
    });

    it('creates paid income using factory state', function () {
        $income = OtherIncome::factory()->paid()->create(['amount' => 10000]);
        $income->refresh();

        expect($income->status)->toBe(OtherIncomeStatus::Paid);
        expect($income->amount_paid)->toBe(10000);
        expect($income->paid_at)->not->toBeNull();
        expect($income->isExactPayment())->toBeTrue();
    });

    it('detects overpayment correctly', function () {
        $income = OtherIncome::factory()->create(['amount' => 10000]);

        $income->markAsPaid(amountPaid: 12000);

        expect($income->status)->toBe(OtherIncomeStatus::Paid);
        expect($income->isOverpayment())->toBeTrue();
        expect($income->getOverpaymentAmount())->toBe(2000);
        expect($income->getOutstandingAmount())->toBe(0);
    });
});

describe('BankAccount Model', function () {
    it('creates a bank account with factory', function () {
        $account = BankAccount::factory()->create();

        expect($account->name)->not->toBeEmpty();
        expect($account->is_active)->toBeTrue();
    });

    it('scopes active accounts', function () {
        BankAccount::factory()->create(['is_active' => true]);
        BankAccount::factory()->create(['is_active' => false]);

        expect(BankAccount::active()->count())->toBe(1);
    });

    it('can have other incomes', function () {
        $account = BankAccount::factory()->create();
        $person = Person::factory()->create();

        OtherIncome::factory()->count(3)->create([
            'bank_account_id' => $account->id,
            'person_id' => $person->id,
        ]);

        expect($account->otherIncomes)->toHaveCount(3);
    });
});

describe('BankAccount Filament Resource', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('can render the list page', function () {
        $response = $this->get('/admin/bank-accounts');

        $response->assertSuccessful();
    });

    it('can render the create page', function () {
        $response = $this->get('/admin/bank-accounts/create');

        $response->assertSuccessful();
    });

    it('can render the edit page', function () {
        $account = BankAccount::factory()->create();

        $response = $this->get("/admin/bank-accounts/{$account->id}/edit");

        $response->assertSuccessful();
    });
});
