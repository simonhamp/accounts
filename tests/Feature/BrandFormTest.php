<?php

use App\Filament\Resources\Brands\Pages\CreateBrand;
use App\Filament\Resources\Brands\Pages\EditBrand;
use App\Filament\Resources\Brands\Pages\ListBrands;
use App\Models\Brand;
use App\Models\Invoice;
use App\Models\StripeAccount;
use App\Models\User;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Symfony\Component\Mailer\Exception\TransportException;

/**
 * @return array<string, mixed>
 */
function completeBrandForm(array $overrides = []): array
{
    return [
        'name' => 'Clickback',
        'email_invoices' => true,
        'invoice_sender_name' => 'Clickback',
        'invoice_from_address' => 'invoices@clickback.test',
        'invoice_reply_to' => 'support@clickback.test',
        'invoice_bcc' => 'copies@sinoperro.test',
        'invoice_language' => 'en',
        'smtp_host' => 'smtp.eu.mailgun.test',
        'smtp_port' => 465,
        'smtp_scheme' => 'smtps',
        'smtp_username' => 'postmaster@mail.clickback.test',
        'smtp_password' => 'a-very-secret-password',
        ...$overrides,
    ];
}

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create(['email' => 'simon@sinoperro.test']));
});

it('lists brands', function () {
    $brands = Brand::factory()->count(2)->create();

    Livewire::test(ListBrands::class)
        ->assertCanSeeTableRecords($brands);
});

it('creates a brand with its invoice email and mail server settings', function () {
    Livewire::test(CreateBrand::class)
        ->assertFormSet(['email_invoices' => false, 'invoice_language' => 'en'])
        ->fillForm(completeBrandForm())
        ->call('create')
        ->assertHasNoFormErrors();

    $brand = Brand::sole();

    expect($brand->only([
        'name', 'email_invoices', 'invoice_sender_name', 'invoice_from_address', 'invoice_reply_to',
        'invoice_bcc', 'invoice_language', 'smtp_host', 'smtp_port', 'smtp_scheme', 'smtp_username', 'smtp_password',
    ]))->toBe(completeBrandForm())
        ->and(DB::table('brands')->value('smtp_password'))->not->toContain('a-very-secret-password');
});

it('needs a name', function () {
    Livewire::test(CreateBrand::class)
        ->fillForm(['name' => ''])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);
});

it('needs the sender and mail server once invoice emails are on', function () {
    Livewire::test(CreateBrand::class)
        ->fillForm(['name' => 'Estilistas', 'email_invoices' => true])
        ->call('create')
        ->assertHasFormErrors([
            'invoice_sender_name' => 'required',
            'invoice_from_address' => 'required',
            'smtp_host' => 'required',
            'smtp_port' => 'required',
            'smtp_username' => 'required',
            'smtp_password' => 'required',
        ]);
});

it('needs nothing else while invoice emails are off', function () {
    Livewire::test(CreateBrand::class)
        ->fillForm(['name' => 'Estilistas', 'email_invoices' => false])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Brand::sole()->smtp_password)->toBeNull();
});

it('checks addresses, the port and the language', function () {
    Livewire::test(CreateBrand::class)
        ->fillForm(completeBrandForm([
            'invoice_from_address' => 'not-an-email',
            'invoice_reply_to' => 'nope',
            'invoice_bcc' => 'still nope',
            'smtp_port' => 70000,
            'invoice_language' => 'fr',
        ]))
        ->call('create')
        ->assertHasFormErrors([
            'invoice_from_address' => 'email',
            'invoice_reply_to' => 'email',
            'invoice_bcc' => 'email',
            'smtp_port' => 'max',
            'invoice_language',
        ]);
});

it('never sends the saved password back to the form', function () {
    $brand = Brand::factory()->emailingInvoices()->create(['smtp_password' => 'a-very-secret-password']);

    Livewire::test(EditBrand::class, ['record' => $brand->id])
        ->assertFormSet(['smtp_password' => null])
        ->assertDontSee('a-very-secret-password');

    expect($brand->toArray())->not->toHaveKey('smtp_password');
});

it('keeps the saved password when the field is left blank', function () {
    $brand = Brand::factory()->emailingInvoices()->create(['smtp_password' => 'a-very-secret-password']);

    Livewire::test(EditBrand::class, ['record' => $brand->id])
        ->fillForm(['smtp_host' => 'smtp.changed.test', 'smtp_password' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($brand->fresh()->smtp_host)->toBe('smtp.changed.test')
        ->and($brand->fresh()->smtp_password)->toBe('a-very-secret-password');
});

it('replaces the saved password when a new one is typed', function () {
    $brand = Brand::factory()->emailingInvoices()->create(['smtp_password' => 'a-very-secret-password']);

    Livewire::test(EditBrand::class, ['record' => $brand->id])
        ->fillForm(['smtp_password' => 'a-brand-new-password'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($brand->fresh()->smtp_password)->toBe('a-brand-new-password');
});

it('clears the brand from accounts and invoices when it is deleted', function () {
    $brand = Brand::factory()->create();
    $account = StripeAccount::factory()->create(['brand_id' => $brand->id]);
    $invoice = Invoice::factory()->create(['brand_id' => $brand->id]);

    Livewire::test(EditBrand::class, ['record' => $brand->id])
        ->callAction('delete');

    expect(Brand::count())->toBe(0)
        ->and($account->fresh()->brand_id)->toBeNull()
        ->and($invoice->fresh()->brand_id)->toBeNull();
});

describe('send test email', function () {
    it('is disabled until the mail settings are complete', function () {
        $brand = Brand::factory()->create(['email_invoices' => false]);

        Livewire::test(EditBrand::class, ['record' => $brand->id])
            ->assertActionDisabled('sendTestEmail');
    });

    it('sends a short message through the brand\'s mail server, even with invoice emails off', function () {
        $built = new ArrayObject;
        Mail::extend('smtp', function (array $config) use ($built) {
            $transport = new ArrayTransport;
            $built[] = ['config' => $config, 'transport' => $transport];

            return $transport;
        });

        $brand = Brand::factory()->emailingInvoices()->create([
            'name' => 'Estilistas',
            'email_invoices' => false,
            'invoice_sender_name' => 'Estilistas',
            'invoice_from_address' => 'facturas@estilistas.test',
            'smtp_host' => 'smtp.estilistas.test',
            'smtp_port' => 587,
            'smtp_password' => 'estilistas-secret',
        ]);

        Livewire::test(EditBrand::class, ['record' => $brand->id])
            ->assertActionEnabled('sendTestEmail')
            ->mountAction('sendTestEmail')
            ->assertActionDataSet(['to' => 'simon@sinoperro.test'])
            ->setActionData(['to' => 'check@example.test'])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('Test email sent');

        expect($built)->toHaveCount(1)
            ->and($built[0]['config']['host'])->toBe('smtp.estilistas.test')
            ->and($built[0]['config']['password'])->toBe('estilistas-secret');

        $email = $built[0]['transport']->messages()->sole()->getOriginalMessage();

        expect($email->getTo()[0]->getAddress())->toBe('check@example.test')
            ->and($email->getFrom()[0]->getAddress())->toBe('facturas@estilistas.test')
            ->and($email->getFrom()[0]->getName())->toBe('Estilistas')
            ->and($email->getSubject())->toBe('Test email for Estilistas invoices')
            ->and($email->getTextBody())->toContain("sent through Estilistas's mail server (smtp.estilistas.test:587)");
    });

    it('shows a failure without the password in it', function () {
        Mail::extend('smtp', fn () => throw new TransportException('535 Authentication failed for password "estilistas-secret"'));

        $brand = Brand::factory()->emailingInvoices()->create([
            'name' => 'Estilistas',
            'smtp_password' => 'estilistas-secret',
        ]);

        Livewire::test(EditBrand::class, ['record' => $brand->id])
            ->callAction('sendTestEmail', data: ['to' => 'check@example.test']);

        $notification = collect(session('filament.notifications'))->firstWhere('title', 'Test email failed');

        expect($notification)->not->toBeNull()
            ->and($notification['body'])
            ->toContain('535 Authentication failed for password')
            ->not->toContain('estilistas-secret');
    });
});
