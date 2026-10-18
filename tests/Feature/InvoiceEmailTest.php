<?php

use App\Enums\InvoiceStatus;
use App\Exceptions\BrandMailException;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Jobs\SendInvoiceEmail;
use App\Mail\InvoiceMail;
use App\Models\Brand;
use App\Models\Invoice;
use App\Models\Person;
use App\Models\StripeAccount;
use App\Models\StripeTransaction;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\StripeImportService;
use Filament\Actions\Action;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Email;

/**
 * Route every brand mailer to an in-memory transport, and record the SMTP
 * config each one was built with.
 *
 * @return ArrayObject<int, array{config: array<string, mixed>, transport: ArrayTransport}>
 */
function captureBrandMailers(): ArrayObject
{
    $built = new ArrayObject;

    Mail::extend('smtp', function (array $config) use ($built) {
        $transport = new ArrayTransport;
        $built[] = ['config' => $config, 'transport' => $transport];

        return $transport;
    });

    return $built;
}

/**
 * @param  ArrayObject<int, array{config: array<string, mixed>, transport: ArrayTransport}>  $built
 * @return Collection<int, Email>
 */
function sentThroughBrands(ArrayObject $built): Collection
{
    return collect($built->getArrayCopy())
        ->flatMap(fn (array $mailer) => $mailer['transport']->messages()->map->getOriginalMessage());
}

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

    $this->brand = Brand::factory()->emailingInvoices()->create([
        'name' => 'Clickback',
        'invoice_sender_name' => 'Clickback',
        'invoice_from_address' => 'invoices@clickback.test',
        'invoice_reply_to' => 'support@clickback.test',
        'invoice_bcc' => 'copies@sinoperro.test',
        'invoice_language' => 'en',
        'smtp_host' => 'smtp.clickback.test',
        'smtp_port' => 465,
        'smtp_username' => 'postmaster@clickback.test',
        'smtp_password' => 'clickback-smtp-secret',
    ]);

    $this->account = StripeAccount::factory()->create([
        'person_id' => $this->person->id,
        'brand_id' => $this->brand->id,
        'account_name' => 'Clickback',
    ]);
});

describe('sending on generation', function () {
    it('gives each invoice its Stripe account\'s brand', function () {
        Queue::fake();
        readyStripeTransaction($this->account);

        $unbranded = StripeAccount::factory()->create(['person_id' => $this->person->id, 'brand_id' => null]);
        readyStripeTransaction($unbranded, ['transaction_date' => now()]);

        app(InvoiceService::class)->generateInvoicesForReadyTransactions();

        expect(Invoice::firstWhere('invoice_number', 'SP-00001')->brand_id)->toBe($this->brand->id)
            ->and(Invoice::firstWhere('invoice_number', 'SP-00002')->brand_id)->toBeNull();
    });

    it('queues an email for each invoice the run makes', function () {
        Queue::fake();
        readyStripeTransaction($this->account);

        $result = generateReadyInvoices($this->account);

        $invoice = Invoice::sole();

        expect($result['generated'])->toBe(1)
            ->and($result['emailed'])->toBe(1);

        Queue::assertPushed(SendInvoiceEmail::class, fn (SendInvoiceEmail $job) => $job->invoice->is($invoice));
    });

    it('does not email when the brand has invoice emails turned off', function () {
        Queue::fake();
        $this->brand->update(['email_invoices' => false]);
        readyStripeTransaction($this->account);

        $result = generateReadyInvoices($this->account);

        expect($result['generated'])->toBe(1)
            ->and($result['emailed'])->toBe(0)
            ->and($result['not_emailed'])->toBe([]);
        Queue::assertNothingPushed();
    });

    it('does not email invoices whose account has no brand', function () {
        Queue::fake();
        $this->account->update(['brand_id' => null]);
        readyStripeTransaction($this->account);

        $result = generateReadyInvoices($this->account);

        expect($result['generated'])->toBe(1)
            ->and($result['emailed'])->toBe(0)
            ->and(Invoice::sole()->emailWarning())->toBeNull();
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
        $this->brand->update(['email_invoices' => false]);
        readyStripeTransaction($this->account);
        generateReadyInvoices($this->account);

        $this->brand->update(['email_invoices' => true]);
        $result = generateReadyInvoices($this->account);

        expect($result['generated'])->toBe(0)
            ->and(Invoice::sole()->status)->toBe(InvoiceStatus::ReadyToSend);
        Queue::assertNothingPushed();
    });

    it('leaves an invoice that can\'t be emailed ready to send and says why', function () {
        Queue::fake();
        readyStripeTransaction($this->account, ['customer_email' => null]);

        $result = generateReadyInvoices($this->account);

        $invoice = Invoice::sole();

        expect($result['emailed'])->toBe(0)
            ->and($result['not_emailed'])->toBe([[
                'invoice_number' => 'SP-00001',
                'reason' => 'Add a customer email to the invoice and save it first.',
            ]])
            ->and($invoice->status)->toBe(InvoiceStatus::ReadyToSend)
            ->and($invoice->emailWarning())->toBe('Add a customer email to the invoice and save it first.');
        Queue::assertNothingPushed();
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
    it('goes out through the brand\'s own mail server and records who it went to', function () {
        $mailers = captureBrandMailers();
        readyStripeTransaction($this->account);

        generateReadyInvoices($this->account);

        expect($mailers)->toHaveCount(1)
            ->and($mailers[0]['config'])->toMatchArray([
                'transport' => 'smtp',
                'host' => 'smtp.clickback.test',
                'port' => 465,
                'scheme' => null,
                'username' => 'postmaster@clickback.test',
                'password' => 'clickback-smtp-secret',
            ]);

        $email = sentThroughBrands($mailers)->sole();
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
            ->and($attachment->getBody())->toBe(Storage::get(Invoice::sole()->pdf_path_en));

        $invoice = Invoice::sole();

        expect($invoice->status)->toBe(InvoiceStatus::Sent)
            ->and($invoice->sent_to)->toBe('billing@acme.test')
            ->and($invoice->sent_at->toDateTimeString())->toBe('2026-10-17 12:00:00');
    });

    it('never touches the app\'s default mailer', function () {
        captureBrandMailers();
        readyStripeTransaction($this->account);

        generateReadyInvoices($this->account);

        expect(app('mailer')->getSymfonyTransport()->messages())->toHaveCount(0);
    });

    it('uses each brand\'s own settings', function () {
        $mailers = captureBrandMailers();

        $laradevs = Brand::factory()->emailingInvoices()->create([
            'name' => 'Laradevs',
            'invoice_sender_name' => 'Laradevs',
            'invoice_from_address' => 'billing@laradevs.test',
            'invoice_language' => 'es',
            'smtp_host' => 'smtp.laradevs.test',
            'smtp_port' => 587,
            'smtp_scheme' => 'smtp',
            'smtp_username' => 'laradevs',
            'smtp_password' => 'laradevs-smtp-secret',
        ]);
        $laradevsAccount = StripeAccount::factory()->create([
            'person_id' => $this->person->id,
            'brand_id' => $laradevs->id,
        ]);

        readyStripeTransaction($this->account, ['transaction_date' => now()->subDays(2)]);
        readyStripeTransaction($laradevsAccount, ['customer_email' => 'dev@client.test']);

        app(InvoiceService::class)->generateInvoicesForReadyTransactions(emailCustomers: true);

        expect(collect($mailers->getArrayCopy())->pluck('config')->map(fn (array $config) => Arr::only($config, ['host', 'port', 'scheme', 'username', 'password']))->all())->toEqual([
            ['host' => 'smtp.clickback.test', 'port' => 465, 'scheme' => null, 'username' => 'postmaster@clickback.test', 'password' => 'clickback-smtp-secret'],
            ['host' => 'smtp.laradevs.test', 'port' => 587, 'scheme' => 'smtp', 'username' => 'laradevs', 'password' => 'laradevs-smtp-secret'],
        ]);

        $clickbackEmail = $mailers[0]['transport']->messages()->sole()->getOriginalMessage();
        $laradevsEmail = $mailers[1]['transport']->messages()->sole()->getOriginalMessage();

        expect($clickbackEmail->getFrom()[0]->getAddress())->toBe('invoices@clickback.test')
            ->and($clickbackEmail->getSubject())->toBe('Invoice SP-00001 from Clickback')
            ->and($laradevsEmail->getFrom()[0]->getAddress())->toBe('billing@laradevs.test')
            ->and($laradevsEmail->getTo()[0]->getAddress())->toBe('dev@client.test')
            ->and($laradevsEmail->getSubject())->toBe('Factura SP-00002 de Laradevs')
            ->and($laradevsEmail->getAttachments()[0]->getBody())
            ->toBe(Storage::get(Invoice::firstWhere('invoice_number', 'SP-00002')->pdf_path));
    });

    it('builds the mailer from the settings saved when it sends', function () {
        Queue::fake();
        $mailers = captureBrandMailers();
        readyStripeTransaction($this->account);
        generateReadyInvoices($this->account);

        $this->brand->update(['smtp_host' => 'smtp.new-provider.test', 'smtp_password' => 'rotated-secret']);
        (new SendInvoiceEmail(Invoice::sole()))->handle(app(InvoiceService::class));

        expect($mailers[0]['config']['host'])->toBe('smtp.new-provider.test')
            ->and($mailers[0]['config']['password'])->toBe('rotated-secret');
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

    it('writes in Spanish and attaches the Spanish PDF when the brand says so', function () {
        $mailers = captureBrandMailers();
        $this->brand->update(['invoice_language' => 'es']);
        readyStripeTransaction($this->account, ['amount' => 123456]);

        generateReadyInvoices($this->account);

        $invoice = Invoice::sole();
        $email = sentThroughBrands($mailers)->sole();

        expect($email->getSubject())->toBe('Factura SP-00001 de Clickback')
            ->and($email->getAttachments()[0]->getBody())->toBe(Storage::get($invoice->pdf_path))
            ->and($email->getTextBody())
            ->toContain('Adjuntamos la factura de Clickback.')
            ->toContain('Fecha: 16 de octubre de 2026')
            ->toContain('Total: 1.234,56 EUR')
            ->toContain('Esta factura la emite SINOPERRO, S.L. (CIF B26722488).');
    });

    it('makes the PDF first when it does not exist yet', function () {
        $mailers = captureBrandMailers();
        $invoice = Invoice::factory()->readyToSend()->create([
            'person_id' => $this->person->id,
            'brand_id' => $this->brand->id,
            'customer_email' => 'someone@example.test',
            'pdf_path' => null,
            'pdf_path_en' => null,
        ]);

        SendInvoiceEmail::dispatch($invoice);

        $invoice->refresh();

        expect($invoice->pdf_path_en)->not->toBeNull()
            ->and(Storage::exists($invoice->pdf_path_en))->toBeTrue()
            ->and($invoice->sent_to)->toBe('someone@example.test')
            ->and(sentThroughBrands($mailers)->sole()->getAttachments()[0]->getBody())->toBe(Storage::get($invoice->pdf_path_en));
    });

    it('keeps a paid invoice paid when it is resent', function () {
        captureBrandMailers();
        $invoice = Invoice::factory()->paid()->create([
            'person_id' => $this->person->id,
            'brand_id' => $this->brand->id,
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

    it('skips an invoice that can no longer be emailed when the job runs', function () {
        $mailers = captureBrandMailers();
        $invoice = Invoice::factory()->readyToSend()->create([
            'person_id' => $this->person->id,
            'brand_id' => $this->brand->id,
            'customer_email' => 'someone@example.test',
        ]);
        $this->brand->update(['email_invoices' => false]);

        SendInvoiceEmail::dispatch($invoice);

        expect($mailers)->toHaveCount(0)
            ->and($invoice->fresh()->sent_at)->toBeNull()
            ->and($invoice->fresh()->status)->toBe(InvoiceStatus::ReadyToSend);
    });

    it('fails without the SMTP password anywhere in the exception', function () {
        Mail::extend('smtp', fn () => throw new TransportException(
            'Failed to authenticate with password "clickback-smtp-secret" (AUTH '.base64_encode('clickback-smtp-secret').')'
        ));
        $invoice = Invoice::factory()->readyToSend()->create([
            'person_id' => $this->person->id,
            'brand_id' => $this->brand->id,
            'customer_email' => 'someone@example.test',
        ]);

        try {
            (new SendInvoiceEmail($invoice))->handle(app(InvoiceService::class));
            $this->fail('The job should have failed.');
        } catch (BrandMailException $e) {
            expect((string) $e)
                ->toContain("Couldn't send invoice {$invoice->invoice_number} through Clickback's mail server (smtp.clickback.test:465)")
                ->not->toContain('clickback-smtp-secret')
                ->not->toContain(base64_encode('clickback-smtp-secret'))
                ->and($e->getPrevious())->toBeNull();
        }

        expect($invoice->fresh()->sent_at)->toBeNull();
    });
});

describe('why an invoice can\'t be emailed', function () {
    it('gives a clear reason for each case', function (Closure $setUp, ?string $reason) {
        $invoice = Invoice::factory()->readyToSend()->create([
            'person_id' => $this->person->id,
            'brand_id' => $this->brand->id,
            'customer_email' => 'someone@example.test',
        ]);

        $setUp($invoice, $this->brand);

        $invoice = $invoice->fresh();

        expect($invoice->emailBlocker())->toBe($reason)
            ->and($invoice->canBeEmailed())->toBe($reason === null);
    })->with([
        'ready' => [fn () => null, null],
        'not finalized' => [fn (Invoice $invoice) => $invoice->forceFill(['status' => InvoiceStatus::Reviewed])->saveQuietly(), 'Only finalized invoices can be emailed.'],
        'no brand' => [fn (Invoice $invoice) => $invoice->forceFill(['brand_id' => null])->saveQuietly(), 'Pick a brand for this invoice first.'],
        'emails off' => [fn (Invoice $invoice, Brand $brand) => $brand->update(['email_invoices' => false]), 'Invoice emails are turned off for Clickback.'],
        'no mail server' => [fn (Invoice $invoice, Brand $brand) => $brand->update(['smtp_host' => null]), "Clickback's sender or mail server settings aren't complete."],
        'no password' => [fn (Invoice $invoice, Brand $brand) => $brand->update(['smtp_password' => null]), "Clickback's sender or mail server settings aren't complete."],
        'no from address' => [fn (Invoice $invoice, Brand $brand) => $brand->update(['invoice_from_address' => null]), "Clickback's sender or mail server settings aren't complete."],
        'no customer email' => [fn (Invoice $invoice) => $invoice->forceFill(['customer_email' => null])->saveQuietly(), 'Add a customer email to the invoice and save it first.'],
    ]);

    it('only flags invoices whose brand emails invoices', function () {
        $flagged = Invoice::factory()->readyToSend()->create([
            'person_id' => $this->person->id,
            'brand_id' => $this->brand->id,
            'customer_email' => null,
        ]);
        $quietBrand = Brand::factory()->create();
        $notFlagged = Invoice::factory()->readyToSend()->create([
            'person_id' => $this->person->id,
            'brand_id' => $quietBrand->id,
            'customer_email' => null,
        ]);
        $noBrand = Invoice::factory()->readyToSend()->create([
            'person_id' => $this->person->id,
            'customer_email' => null,
        ]);

        expect($flagged->emailWarning())->toBe('Add a customer email to the invoice and save it first.')
            ->and($notFlagged->emailWarning())->toBeNull()
            ->and($noBrand->emailWarning())->toBeNull();
    });
});

describe('sending by hand', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->admin()->create());
    });

    it('sends from the edit page after confirming the brand and recipient', function () {
        $mailers = captureBrandMailers();
        $this->brand->update(['email_invoices' => true]);
        readyStripeTransaction($this->account);
        app(InvoiceService::class)->generateInvoicesForReadyTransactions($this->account);
        $invoice = Invoice::sole();

        Livewire::test(EditInvoice::class, ['record' => $invoice->id])
            ->assertActionVisible('emailInvoice')
            ->assertActionEnabled('emailInvoice')
            ->assertActionHasLabel('emailInvoice', 'Send')
            ->mountAction('emailInvoice')
            ->assertMountedActionModalSee([
                'billing@acme.test',
                'as Clickback',
                'Clickback <invoices@clickback.test>',
                'through smtp.clickback.test',
                'copies@sinoperro.test',
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        expect(sentThroughBrands($mailers)->sole()->getTo()[0]->getAddress())->toBe('billing@acme.test')
            ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Sent)
            ->and($invoice->fresh()->sent_to)->toBe('billing@acme.test');
    });

    it('offers to resend an invoice that has been sent', function () {
        $mailers = captureBrandMailers();
        $invoice = Invoice::factory()->sent()->create([
            'person_id' => $this->person->id,
            'brand_id' => $this->brand->id,
            'customer_email' => 'someone@example.test',
            'sent_at' => now()->subDay(),
            'sent_to' => 'someone@example.test',
        ]);

        Livewire::test(EditInvoice::class, ['record' => $invoice->id])
            ->assertActionHasLabel('emailInvoice', 'Resend')
            ->callAction('emailInvoice');

        expect(sentThroughBrands($mailers))->toHaveCount(1)
            ->and($invoice->fresh()->sent_at->toDateTimeString())->toBe('2026-10-17 12:00:00');
    });

    it('disables sending and says why', function () {
        $invoice = Invoice::factory()->readyToSend()->create([
            'person_id' => $this->person->id,
            'customer_email' => 'someone@example.test',
        ]);

        Livewire::test(EditInvoice::class, ['record' => $invoice->id])
            ->assertActionVisible('emailInvoice')
            ->assertActionDisabled('emailInvoice')
            ->assertActionExists('emailInvoice', fn (Action $action) => $action->getTooltip() === 'Pick a brand for this invoice first.');
    });

    it('only offers sending for finalized invoices', function () {
        $invoice = Invoice::factory()->reviewed()->create([
            'person_id' => $this->person->id,
            'brand_id' => $this->brand->id,
            'customer_email' => 'someone@example.test',
        ]);

        Livewire::test(EditInvoice::class, ['record' => $invoice->id])
            ->assertActionHidden('emailInvoice');
    });

    it('shows why an invoice was not emailed on the edit page', function () {
        readyStripeTransaction($this->account, ['customer_email' => null]);
        app(InvoiceService::class)->generateInvoicesForReadyTransactions($this->account);

        Livewire::test(EditInvoice::class, ['record' => Invoice::sole()->id])
            ->assertSee('This invoice was not emailed: Add a customer email to the invoice and save it first.');
    });

    it('lets the brand be picked on the invoice form', function () {
        $invoice = Invoice::factory()->readyToSend()->create([
            'person_id' => $this->person->id,
            'customer_email' => 'someone@example.test',
        ]);

        Livewire::test(EditInvoice::class, ['record' => $invoice->id])
            ->assertFormSet(['brand_id' => null])
            ->fillForm(['brand_id' => $this->brand->id])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($invoice->fresh()->brand_id)->toBe($this->brand->id)
            ->and($invoice->fresh()->canBeEmailed())->toBeTrue();
    });

    it('sends from the invoices table', function () {
        $mailers = captureBrandMailers();
        $invoice = Invoice::factory()->readyToSend()->create([
            'person_id' => $this->person->id,
            'brand_id' => $this->brand->id,
            'customer_email' => 'someone@example.test',
        ]);
        $withoutEmail = Invoice::factory()->readyToSend()->create([
            'person_id' => $this->person->id,
            'brand_id' => $this->brand->id,
            'customer_email' => null,
        ]);

        Livewire::test(ListInvoices::class)
            ->assertTableActionEnabled('emailInvoice', $invoice)
            ->assertTableActionDisabled('emailInvoice', $withoutEmail)
            ->callTableAction('emailInvoice', $invoice);

        expect(sentThroughBrands($mailers)->sole()->getTo()[0]->getAddress())->toBe('someone@example.test');
    });

    it('flags invoices that were not emailed in the table', function () {
        readyStripeTransaction($this->account, ['customer_email' => null]);
        app(InvoiceService::class)->generateInvoicesForReadyTransactions($this->account);

        Livewire::test(ListInvoices::class)
            ->assertTableColumnStateSet('email_warning', true, Invoice::sole());
    });

    it('emails the selected invoices that can be emailed in bulk and says why others are skipped', function () {
        Queue::fake();
        $sendable = Invoice::factory()->readyToSend()->create([
            'person_id' => $this->person->id,
            'brand_id' => $this->brand->id,
            'customer_email' => 'someone@example.test',
        ]);
        $withoutEmail = Invoice::factory()->readyToSend()->create([
            'person_id' => $this->person->id,
            'brand_id' => $this->brand->id,
            'customer_email' => null,
        ]);
        $withoutBrand = Invoice::factory()->readyToSend()->create([
            'person_id' => $this->person->id,
            'customer_email' => 'other@example.test',
        ]);

        Livewire::test(ListInvoices::class)
            ->mountTableBulkAction('emailInvoices', [$sendable, $withoutEmail, $withoutBrand])
            ->assertMountedActionModalSee([
                '1 invoice(s) will be emailed to their customers, 1 as Clickback.',
                'Add a customer email to the invoice and save it first. (1)',
                'Pick a brand for this invoice first. (1)',
            ])
            ->callMountedTableBulkAction();

        Queue::assertPushed(SendInvoiceEmail::class, 1);
        Queue::assertPushed(SendInvoiceEmail::class, fn (SendInvoiceEmail $job) => $job->invoice->is($sendable));
    });
});
