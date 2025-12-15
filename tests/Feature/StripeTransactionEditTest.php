<?php

use App\Enums\OtherIncomeStatus;
use App\Filament\Resources\StripeTransactions\Pages\EditStripeTransaction;
use App\Models\Invoice;
use App\Models\OtherIncome;
use App\Models\StripeTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('Generate Invoice Action', function () {
    it('shows generate invoice button when transaction is ready and not processed', function () {
        $transaction = StripeTransaction::factory()->create([
            'status' => 'ready',
        ]);

        Livewire::test(EditStripeTransaction::class, ['record' => $transaction->id])
            ->assertSuccessful()
            ->assertActionVisible('generate_invoice');
    });

    it('hides generate invoice button when transaction is already invoiced', function () {
        $transaction = StripeTransaction::factory()->create([
            'status' => 'ready',
        ]);

        // Create an invoice item for this transaction to mark it as invoiced
        $invoice = Invoice::factory()->create([
            'person_id' => $transaction->stripeAccount->person_id,
        ]);

        $transaction->invoiceItem()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Test item',
            'quantity' => 1,
            'unit_price' => $transaction->amount,
            'total' => $transaction->amount,
        ]);

        Livewire::test(EditStripeTransaction::class, ['record' => $transaction->id])
            ->assertSuccessful()
            ->assertActionHidden('generate_invoice')
            ->assertActionVisible('view_invoice');
    });

    it('hides generate invoice button when transaction is already other income', function () {
        $transaction = StripeTransaction::factory()->create([
            'status' => 'ready',
        ]);

        OtherIncome::create([
            'person_id' => $transaction->stripeAccount->person_id,
            'stripe_transaction_id' => $transaction->id,
            'income_date' => $transaction->transaction_date,
            'description' => $transaction->description,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'status' => OtherIncomeStatus::Paid,
        ]);

        Livewire::test(EditStripeTransaction::class, ['record' => $transaction->id])
            ->assertSuccessful()
            ->assertActionHidden('generate_invoice')
            ->assertActionVisible('view_other_income');
    });

    it('hides generate invoice button when transaction is pending review', function () {
        $transaction = StripeTransaction::factory()->create([
            'status' => 'pending_review',
        ]);

        Livewire::test(EditStripeTransaction::class, ['record' => $transaction->id])
            ->assertSuccessful()
            ->assertActionHidden('generate_invoice');
    });

    it('hides generate invoice button when transaction is ignored', function () {
        $transaction = StripeTransaction::factory()->create([
            'status' => 'ignored',
        ]);

        Livewire::test(EditStripeTransaction::class, ['record' => $transaction->id])
            ->assertSuccessful()
            ->assertActionHidden('generate_invoice');
    });

    it('generates invoice when action is called', function () {
        $transaction = StripeTransaction::factory()->create([
            'status' => 'ready',
        ]);

        expect(Invoice::count())->toBe(0);

        Livewire::test(EditStripeTransaction::class, ['record' => $transaction->id])
            ->callAction('generate_invoice')
            ->assertHasNoActionErrors();

        expect(Invoice::count())->toBe(1);
        expect($transaction->fresh()->isInvoiced())->toBeTrue();
    });
});

describe('View Invoice Action', function () {
    it('shows view invoice button when transaction is invoiced', function () {
        $transaction = StripeTransaction::factory()->create([
            'status' => 'ready',
        ]);

        $invoice = Invoice::factory()->create([
            'person_id' => $transaction->stripeAccount->person_id,
        ]);

        $transaction->invoiceItem()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Test item',
            'quantity' => 1,
            'unit_price' => $transaction->amount,
            'total' => $transaction->amount,
        ]);

        Livewire::test(EditStripeTransaction::class, ['record' => $transaction->id])
            ->assertSuccessful()
            ->assertActionVisible('view_invoice');
    });

    it('hides view invoice button when transaction is not invoiced', function () {
        $transaction = StripeTransaction::factory()->create([
            'status' => 'ready',
        ]);

        Livewire::test(EditStripeTransaction::class, ['record' => $transaction->id])
            ->assertSuccessful()
            ->assertActionHidden('view_invoice');
    });
});

describe('View in Stripe Action', function () {
    it('always shows view in stripe button', function () {
        $transaction = StripeTransaction::factory()->create([
            'type' => 'payment',
        ]);

        Livewire::test(EditStripeTransaction::class, ['record' => $transaction->id])
            ->assertSuccessful()
            ->assertActionVisible('view_in_stripe');
    });
});

describe('Convert to Other Income Action', function () {
    it('shows convert button when transaction is ready and not processed', function () {
        $transaction = StripeTransaction::factory()->create([
            'status' => 'ready',
        ]);

        Livewire::test(EditStripeTransaction::class, ['record' => $transaction->id])
            ->assertSuccessful()
            ->assertActionVisible('convert_to_other_income');
    });

    it('hides convert button when transaction is already invoiced', function () {
        $transaction = StripeTransaction::factory()->create([
            'status' => 'ready',
        ]);

        $invoice = Invoice::factory()->create([
            'person_id' => $transaction->stripeAccount->person_id,
        ]);

        $transaction->invoiceItem()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Test item',
            'quantity' => 1,
            'unit_price' => $transaction->amount,
            'total' => $transaction->amount,
        ]);

        Livewire::test(EditStripeTransaction::class, ['record' => $transaction->id])
            ->assertSuccessful()
            ->assertActionHidden('convert_to_other_income');
    });

    it('hides convert button when transaction is already other income', function () {
        $transaction = StripeTransaction::factory()->create([
            'status' => 'ready',
        ]);

        OtherIncome::create([
            'person_id' => $transaction->stripeAccount->person_id,
            'stripe_transaction_id' => $transaction->id,
            'income_date' => $transaction->transaction_date,
            'description' => $transaction->description,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'status' => OtherIncomeStatus::Paid,
        ]);

        Livewire::test(EditStripeTransaction::class, ['record' => $transaction->id])
            ->assertSuccessful()
            ->assertActionHidden('convert_to_other_income');
    });

    it('hides convert button when transaction is pending review', function () {
        $transaction = StripeTransaction::factory()->create([
            'status' => 'pending_review',
        ]);

        Livewire::test(EditStripeTransaction::class, ['record' => $transaction->id])
            ->assertSuccessful()
            ->assertActionHidden('convert_to_other_income');
    });

    it('hides convert button when transaction is ignored', function () {
        $transaction = StripeTransaction::factory()->create([
            'status' => 'ignored',
        ]);

        Livewire::test(EditStripeTransaction::class, ['record' => $transaction->id])
            ->assertSuccessful()
            ->assertActionHidden('convert_to_other_income');
    });

    it('converts transaction to other income when action is called', function () {
        $transaction = StripeTransaction::factory()->create([
            'status' => 'ready',
        ]);

        expect(OtherIncome::count())->toBe(0);

        Livewire::test(EditStripeTransaction::class, ['record' => $transaction->id])
            ->callAction('convert_to_other_income')
            ->assertHasNoActionErrors();

        expect(OtherIncome::count())->toBe(1);
        expect($transaction->fresh()->isOtherIncome())->toBeTrue();

        $otherIncome = OtherIncome::first();
        expect($otherIncome->stripe_transaction_id)->toBe($transaction->id);
        expect($otherIncome->amount)->toBe($transaction->amount);
        expect($otherIncome->currency)->toBe($transaction->currency);
        expect($otherIncome->description)->toBe($transaction->description);
        expect($otherIncome->reference)->toBe($transaction->stripe_transaction_id);
        expect($otherIncome->status)->toBe(OtherIncomeStatus::Paid);
    });
});

describe('View Other Income Action', function () {
    it('shows view other income button when transaction is converted', function () {
        $transaction = StripeTransaction::factory()->create([
            'status' => 'ready',
        ]);

        OtherIncome::create([
            'person_id' => $transaction->stripeAccount->person_id,
            'stripe_transaction_id' => $transaction->id,
            'income_date' => $transaction->transaction_date,
            'description' => $transaction->description,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'status' => OtherIncomeStatus::Paid,
        ]);

        Livewire::test(EditStripeTransaction::class, ['record' => $transaction->id])
            ->assertSuccessful()
            ->assertActionVisible('view_other_income');
    });

    it('hides view other income button when transaction is not converted', function () {
        $transaction = StripeTransaction::factory()->create([
            'status' => 'ready',
        ]);

        Livewire::test(EditStripeTransaction::class, ['record' => $transaction->id])
            ->assertSuccessful()
            ->assertActionHidden('view_other_income');
    });
});
