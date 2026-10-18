<?php

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Jobs\SendInvoiceEmail;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Models\Person;
use App\Models\StripeAccount;
use App\Models\StripeTransaction;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\StripeImportService;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * @param  array<string, mixed>  $attributes
 */
function readyStripeTransaction(StripeAccount $account, array $attributes = []): StripeTransaction
{
    return StripeTransaction::factory()->create([
        'stripe_account_id' => $account->id,
        'type' => 'payment',
        'amount' => 5000,
        'currency' => 'EUR',
        'customer_name' => 'Acme GmbH',
        'customer_email' => 'billing@acme.test',
        'customer_address' => 'Hauptstrasse 1, Berlin, 10115, DE',
        'customer_tax_ids' => [['type' => 'eu_vat', 'value' => 'DE123456789']],
        'description' => 'Clickback ad credit',
        'transaction_date' => now()->subDay(),
        'status' => 'ready',
        ...$attributes,
    ]);
}

function generateReadyInvoices(StripeAccount $account): array
{
    return app(InvoiceService::class)->generateInvoicesForReadyTransactions($account, emailCustomers: true);
}

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo('2026-10-17 12:00:00');

    $this->person = Person::query()->where('cif', 'B26722488')->sole();

    $this->account = StripeAccount::factory()->create([
        'person_id' => $this->person->id,
        'account_name' => 'Clickback',
        'email_invoices' => true,
        'invoice_sender_name' => 'Clickback',
        'invoice_from_address' => 'invoices@clickback.test',
        'invoice_reply_to' => 'support@clickback.test',
        'invoice_bcc' => 'copies@sinoperro.test',
        'invoice_language' => 'en',
    ]);
});

describe('sending on generation', function () {
    it('queues an email for each invoice the run makes', function () {
        Queue::fake();
        readyStripeTransaction($this->account);

        $result = generateReadyInvoices($this->account);

        $invoice = Invoice::sole();

        expect($result['generated'])->toBe(1)
            ->and($result['emailed'])->toBe(1);

        Queue::assertPushed(SendInvoiceEmail::class, fn (SendInvoiceEmail $job) => $job->invoice->is($invoice));
    });

    it('does not email when the account has invoice emails turned off', function () {
        Queue::fake();
        $this->account->update(['email_invoices' => false]);
        readyStripeTransaction($this->account);

        $result = generateReadyInvoices($this->account);

        expect($result['generated'])->toBe(1)
            ->and($result['emailed'])->toBe(0);
        Queue::assertNothingPushed();
    });

    it('does not email unless asked to', function () {
        Queue::fake();
        readyStripeTransaction($this->account);

        app(InvoiceService::class)->generateInvoicesForReadyTransactions($this->account);

        expect(Invoice::count())->toBe(1);
        Queue::assertNothingPushed();
    });

    it('never emails invoices that already existed when emails are turned on', function () {
        Queue::fake();
        $this->account->update(['email_invoices' => false]);
        readyStripeTransaction($this->account);
        generateReadyInvoices($this->account);

        $this->account->update(['email_invoices' => true]);
        $result = generateReadyInvoices($this->account);

        expect($result['generated'])->toBe(0)
            ->and(Invoice::sole()->status)->toBe(InvoiceStatus::ReadyToSend);
        Queue::assertNothingPushed();
    });

    it('leaves an invoice without a customer email ready to send and flags it', function () {
        Queue::fake();
        readyStripeTransaction($this->account, ['customer_email' => null]);

        $result = generateReadyInvoices($this->account);

        $invoice = Invoice::sole();

        expect($result['emailed'])->toBe(0)
            ->and($result['missing_email'])->toBe(1)
            ->and($invoice->status)->toBe(InvoiceStatus::ReadyToSend)
            ->and($invoice->needsCustomerEmail())->toBeTrue();
        Queue::assertNothingPushed();
    });

    it('does not flag a missing email on accounts that do not email invoices', function () {
        $this->account->update(['email_invoices' => false]);
        readyStripeTransaction($this->account, ['customer_email' => null]);

        generateReadyInvoices($this->account);

        expect(Invoice::sole()->needsCustomerEmail())->toBeFalse();
    });

    it('does not email invoices for payments older than the scheduled sync window', function () {
        Queue::fake();
        readyStripeTransaction($this->account, ['transaction_date' => now()->subDays(30)]);

        $result = generateReadyInvoices($this->account);

        expect($result['generated'])->toBe(1)
            ->and($result['emailed'])->toBe(0);
        Queue::assertNothingPushed();
    });

    it('emails new invoices on a scheduled sync but never on a backfill', function () {
        Queue::fake();
        $this->mock(StripeImportService::class, function ($mock) {
            $mock->shouldReceive('syncAccount')->andReturn([
                'payments' => 0, 'refunds' => 0, 'disputes' => 0, 'warnings' => [],
            ]);
        });

        readyStripeTransaction($this->account);
        $this->artisan('stripe:sync-transactions', ['--year' => 2026, '--month' => 10])->assertSuccessful();

        expect(Invoice::count())->toBe(1);
        Queue::assertNothingPushed();

        readyStripeTransaction($this->account);
        $this->artisan('stripe:sync-transactions', ['--year' => 2026, '--skip-auto-invoice' => true])->assertSuccessful();

        expect(Invoice::count())->toBe(1);
        Queue::assertNothingPushed();

        $this->artisan('stripe:sync-transactions')
            ->expectsOutputToContain('Queued 1 invoice email(s) to customers')
            ->assertSuccessful();

        expect(Invoice::count())->toBe(2);
        Queue::assertPushed(SendInvoiceEmail::class, 1);
    });
});

describe('the email', function () {
    it('sends the English PDF from the account and records who it went to', function () {
        Mail::fake();
        readyStripeTransaction($this->account);
        generateReadyInvoices($this->account);

        $invoice = Invoice::sole()->refresh();

        Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail) use ($invoice) {
            return $mail->hasTo('billing@acme.test')
                && $mail->hasFrom('invoices@clickback.test', 'Clickback')
                && $mail->hasReplyTo('support@clickback.test')
                && $mail->hasBcc('copies@sinoperro.test')
                && $mail->hasSubject('Invoice SP-00001 from Clickback')
                && $mail->hasAttachment(Attachment::fromStorage($invoice->pdf_path_en)->as('SP-00001.pdf')->withMime('application/pdf'));
        });

        expect($invoice->status)->toBe(InvoiceStatus::Sent)
            ->and($invoice->sent_to)->toBe('billing@acme.test')
            ->and($invoice->sent_at->toDateTimeString())->toBe('2026-10-17 12:00:00');
    });

    it('says who the invoice comes from and who issues it', function () {
        readyStripeTransaction($this->account);
        app(InvoiceService::class)->generateInvoicesForReadyTransactions($this->account);

        $mail = new InvoiceMail(Invoice::sole());

        expect($mail->render())->toBe(<<<'TEXT'
            Hello,

            Your invoice from Clickback is attached.

            Invoice number: SP-00001
            Date: 16 October 2026
            Total: 50.00 EUR

            This invoice is issued by SINOPERRO, S.L. (CIF B26722488).

            TEXT);
    });

    it('calls a refund a credit note and names the invoice it corrects', function () {
        readyStripeTransaction($this->account, [
            'stripe_transaction_id' => 'ch_paid',
            'transaction_date' => now()->subDays(2),
        ]);
        readyStripeTransaction($this->account, [
            'stripe_transaction_id' => 're_refund',
            'stripe_charge_id' => 'ch_paid',
            'type' => 'refund',
            'amount' => -5000,
            'description' => 'Refund: requested_by_customer',
        ]);
        app(InvoiceService::class)->generateInvoicesForReadyTransactions($this->account);

        $mail = new InvoiceMail(Invoice::firstWhere('invoice_number', 'SP-00002'));

        $mail->assertHasSubject('Credit note SP-00002 from Clickback');
        expect($mail->render())->toBe(<<<'TEXT'
            Hello,

            Your credit note from Clickback is attached. It corrects invoice SP-00001.

            Credit note number: SP-00002
            Date: 16 October 2026
            Total: -50.00 EUR

            This credit note is issued by SINOPERRO, S.L. (CIF B26722488).

            TEXT);
    });

    it('attaches the Spanish PDF and writes in Spanish when the account says so', function () {
        Mail::fake();
        $this->account->update(['invoice_language' => 'es']);
        readyStripeTransaction($this->account, ['amount' => 123456, 'customer_email' => 'facturas@cliente.test']);
        generateReadyInvoices($this->account);

        $invoice = Invoice::sole()->refresh();

        Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $mail) => $mail->hasSubject('Factura SP-00001 de Clickback')
            && $mail->hasAttachment(Attachment::fromStorage($invoice->pdf_path)->as('SP-00001.pdf')->withMime('application/pdf'))
            && ! $mail->hasAttachment(Attachment::fromStorage($invoice->pdf_path_en)->as('SP-00001.pdf')->withMime('application/pdf')));

        expect((new InvoiceMail($invoice))->render())
            ->toContain('Adjuntamos la factura de Clickback.')
            ->toContain('Fecha: 16 de octubre de 2026')
            ->toContain('Total: 1.234,56 EUR')
            ->toContain('Esta factura la emite SINOPERRO, S.L. (CIF B26722488).');
    });

    it('goes out through the mailer with the headers and PDF in place', function () {
        readyStripeTransaction($this->account);
        generateReadyInvoices($this->account);

        $messages = app('mailer')->getSymfonyTransport()->messages();

        expect($messages)->toHaveCount(1);

        $email = $messages->first()->getOriginalMessage();
        $attachment = $email->getAttachments()[0];

        expect($email->getFrom()[0]->getName())->toBe('Clickback')
            ->and($email->getFrom()[0]->getAddress())->toBe('invoices@clickback.test')
            ->and($email->getTo()[0]->getAddress())->toBe('billing@acme.test')
            ->and($email->getReplyTo()[0]->getAddress())->toBe('support@clickback.test')
            ->and($email->getBcc()[0]->getAddress())->toBe('copies@sinoperro.test')
            ->and($email->getSubject())->toBe('Invoice SP-00001 from Clickback')
            ->and($email->getTextBody())->toContain('Invoice number: SP-00001')
            ->and($attachment->getFilename())->toBe('SP-00001.pdf')
            ->and($attachment->getMediaType().'/'.$attachment->getMediaSubtype())->toBe('application/pdf')
            ->and($attachment->getBody())->toStartWith('%PDF');
    });

    it('makes the PDF first when it does not exist yet', function () {
        Mail::fake();
        $invoice = Invoice::factory()->readyToSend()->create([
            'person_id' => $this->person->id,
            'customer_email' => 'someone@example.test',
            'pdf_path' => null,
            'pdf_path_en' => null,
        ]);

        SendInvoiceEmail::dispatch($invoice);

        $invoice->refresh();

        expect($invoice->pdf_path_en)->not->toBeNull()
            ->and(Storage::exists($invoice->pdf_path_en))->toBeTrue()
            ->and($invoice->sent_to)->toBe('someone@example.test');
        Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $mail) => $mail->hasAttachment(Attachment::fromStorage($invoice->pdf_path_en)->as($invoice->invoice_number.'.pdf')->withMime('application/pdf')));
    });

    it('uses the default from address and the issuer name for an invoice not made from Stripe', function () {
        Mail::fake();
        config(['mail.from.address' => 'accounts@sinoperro.test']);
        $invoice = Invoice::factory()->readyToSend()->create([
            'person_id' => $this->person->id,
            'customer_email' => 'someone@example.test',
        ]);

        SendInvoiceEmail::dispatch($invoice);

        Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $mail) => $mail->hasFrom('accounts@sinoperro.test', 'SINOPERRO, S.L.')
            && $mail->language() === 'en');
    });

    it('keeps a paid invoice paid when it is resent', function () {
        Mail::fake();
        $invoice = Invoice::factory()->paid()->create([
            'person_id' => $this->person->id,
            'customer_email' => 'someone@example.test',
            'sent_at' => now()->subWeek(),
            'sent_to' => 'old@example.test',
        ]);

        SendInvoiceEmail::dispatch($invoice);

        $invoice->refresh();

        expect($invoice->status)->toBe(InvoiceStatus::Paid)
            ->and($invoice->sent_to)->toBe('someone@example.test')
            ->and($invoice->sent_at->toDateTimeString())->toBe('2026-10-17 12:00:00');
    });

    it('does nothing for an invoice without an email or not yet finalized', function () {
        Mail::fake();
        $withoutEmail = Invoice::factory()->readyToSend()->create(['person_id' => $this->person->id, 'customer_email' => null]);
        $notFinalized = Invoice::factory()->reviewed()->create(['person_id' => $this->person->id, 'customer_email' => 'a@example.test']);

        SendInvoiceEmail::dispatch($withoutEmail);
        SendInvoiceEmail::dispatch($notFinalized);

        Mail::assertNothingSent();
        expect($withoutEmail->fresh()->sent_at)->toBeNull()
            ->and($notFinalized->fresh()->sent_at)->toBeNull();
    });
});

describe('sending by hand', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->admin()->create());
    });

    it('sends from the edit page after confirming the recipient', function () {
        Mail::fake();
        $this->account->update(['email_invoices' => false]);
        readyStripeTransaction($this->account);
        generateReadyInvoices($this->account);
        $invoice = Invoice::sole();

        Livewire::test(EditInvoice::class, ['record' => $invoice->id])
            ->assertActionVisible('emailInvoice')
            ->assertActionEnabled('emailInvoice')
            ->assertActionHasLabel('emailInvoice', 'Send')
            ->mountAction('emailInvoice')
            ->assertMountedActionModalSee([
                'billing@acme.test',
                'Clickback <invoices@clickback.test>',
                'copies@sinoperro.test',
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $mail) => $mail->hasTo('billing@acme.test'));
        expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent)
            ->and($invoice->fresh()->sent_to)->toBe('billing@acme.test');
    });

    it('offers to resend an invoice that has been sent', function () {
        Mail::fake();
        $invoice = Invoice::factory()->sent()->create([
            'person_id' => $this->person->id,
            'customer_email' => 'someone@example.test',
            'sent_at' => now()->subDay(),
            'sent_to' => 'someone@example.test',
        ]);

        Livewire::test(EditInvoice::class, ['record' => $invoice->id])
            ->assertActionHasLabel('emailInvoice', 'Resend')
            ->callAction('emailInvoice');

        Mail::assertSent(InvoiceMail::class, 1);
        expect($invoice->fresh()->sent_at->toDateTimeString())->toBe('2026-10-17 12:00:00');
    });

    it('disables sending without a customer email', function () {
        $invoice = Invoice::factory()->readyToSend()->create([
            'person_id' => $this->person->id,
            'customer_email' => null,
        ]);

        Livewire::test(EditInvoice::class, ['record' => $invoice->id])
            ->assertActionVisible('emailInvoice')
            ->assertActionDisabled('emailInvoice');
    });

    it('only offers sending for finalized invoices', function () {
        $invoice = Invoice::factory()->reviewed()->create([
            'person_id' => $this->person->id,
            'customer_email' => 'someone@example.test',
        ]);

        Livewire::test(EditInvoice::class, ['record' => $invoice->id])
            ->assertActionHidden('emailInvoice');
    });

    it('shows the missing email warning on the edit page', function () {
        readyStripeTransaction($this->account, ['customer_email' => null]);
        app(InvoiceService::class)->generateInvoicesForReadyTransactions($this->account);

        Livewire::test(EditInvoice::class, ['record' => Invoice::sole()->id])
            ->assertSee('No customer email - this invoice was not emailed');
    });

    it('sends from the invoices table', function () {
        Mail::fake();
        $invoice = Invoice::factory()->readyToSend()->create([
            'person_id' => $this->person->id,
            'customer_email' => 'someone@example.test',
        ]);
        $withoutEmail = Invoice::factory()->readyToSend()->create([
            'person_id' => $this->person->id,
            'customer_email' => null,
        ]);

        Livewire::test(ListInvoices::class)
            ->assertTableActionEnabled('emailInvoice', $invoice)
            ->assertTableActionDisabled('emailInvoice', $withoutEmail)
            ->callTableAction('emailInvoice', $invoice);

        Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $mail) => $mail->hasTo('someone@example.test'));
    });

    it('flags invoices without an email in the table', function () {
        readyStripeTransaction($this->account, ['customer_email' => null]);
        app(InvoiceService::class)->generateInvoicesForReadyTransactions($this->account);

        Livewire::test(ListInvoices::class)
            ->assertTableColumnStateSet('needs_customer_email', true, Invoice::sole());
    });

    it('emails the selected invoices that can be emailed in bulk', function () {
        Queue::fake();
        $sendable = Invoice::factory()->readyToSend()->create([
            'person_id' => $this->person->id,
            'customer_email' => 'someone@example.test',
        ]);
        $withoutEmail = Invoice::factory()->readyToSend()->create([
            'person_id' => $this->person->id,
            'customer_email' => null,
        ]);
        $pending = Invoice::factory()->reviewed()->create([
            'person_id' => $this->person->id,
            'customer_email' => 'other@example.test',
        ]);

        Livewire::test(ListInvoices::class)
            ->callTableBulkAction('emailInvoices', [$sendable, $withoutEmail, $pending]);

        Queue::assertPushed(SendInvoiceEmail::class, 1);
        Queue::assertPushed(SendInvoiceEmail::class, fn (SendInvoiceEmail $job) => $job->invoice->is($sendable));
    });
});
