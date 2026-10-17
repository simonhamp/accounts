<?php

use App\Filament\Resources\StripeAccounts\Pages\CreateStripeAccount;
use App\Filament\Resources\StripeAccounts\Pages\EditStripeAccount;
use App\Models\Person;
use App\Models\StripeAccount;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('starts with invoice emails off and the PDF in English', function () {
    Livewire::test(CreateStripeAccount::class)
        ->assertFormFieldExists('email_invoices')
        ->assertFormFieldExists('invoice_sender_name')
        ->assertFormFieldExists('invoice_from_address')
        ->assertFormFieldExists('invoice_reply_to')
        ->assertFormFieldExists('invoice_bcc')
        ->assertFormFieldExists('invoice_language')
        ->assertFormSet([
            'email_invoices' => false,
            'invoice_language' => 'en',
        ]);
});

it('saves the invoice email settings on a new account', function () {
    $person = Person::factory()->create();

    Livewire::test(CreateStripeAccount::class)
        ->fillForm([
            'person_id' => $person->id,
            'account_name' => 'Clickback',
            'api_key' => 'rk_test_123',
            'email_invoices' => true,
            'invoice_sender_name' => 'Clickback',
            'invoice_from_address' => 'invoices@clickback.test',
            'invoice_reply_to' => 'support@clickback.test',
            'invoice_bcc' => 'copies@sinoperro.test',
            'invoice_language' => 'en',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $account = StripeAccount::firstWhere('account_name', 'Clickback');

    expect($account->email_invoices)->toBeTrue()
        ->and($account->invoice_sender_name)->toBe('Clickback')
        ->and($account->invoice_from_address)->toBe('invoices@clickback.test')
        ->and($account->invoice_reply_to)->toBe('support@clickback.test')
        ->and($account->invoice_bcc)->toBe('copies@sinoperro.test')
        ->and($account->invoice_language)->toBe('en')
        ->and($account->api_key)->toBe('rk_test_123');
});

it('needs a sender name and from address once invoice emails are on', function () {
    $account = StripeAccount::factory()->create();

    Livewire::test(EditStripeAccount::class, ['record' => $account->id])
        ->fillForm([
            'email_invoices' => true,
            'invoice_sender_name' => null,
            'invoice_from_address' => null,
        ])
        ->call('save')
        ->assertHasFormErrors([
            'invoice_sender_name' => 'required',
            'invoice_from_address' => 'required',
        ]);
});

it('does not need sender details while invoice emails are off', function () {
    $account = StripeAccount::factory()->create();

    Livewire::test(EditStripeAccount::class, ['record' => $account->id])
        ->fillForm([
            'email_invoices' => false,
            'invoice_sender_name' => null,
            'invoice_from_address' => null,
        ])
        ->call('save')
        ->assertHasNoFormErrors();
});

it('rejects addresses that are not emails', function () {
    $account = StripeAccount::factory()->create();

    Livewire::test(EditStripeAccount::class, ['record' => $account->id])
        ->fillForm([
            'invoice_from_address' => 'not-an-email',
            'invoice_reply_to' => 'nope',
            'invoice_bcc' => 'still nope',
        ])
        ->call('save')
        ->assertHasFormErrors([
            'invoice_from_address' => 'email',
            'invoice_reply_to' => 'email',
            'invoice_bcc' => 'email',
        ]);
});

it('only offers English and Spanish for the PDF', function () {
    $account = StripeAccount::factory()->create();

    Livewire::test(EditStripeAccount::class, ['record' => $account->id])
        ->fillForm(['invoice_language' => 'fr'])
        ->call('save')
        ->assertHasFormErrors(['invoice_language']);

    Livewire::test(EditStripeAccount::class, ['record' => $account->id])
        ->fillForm(['invoice_language' => 'es'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($account->fresh()->invoice_language)->toBe('es');
});

it('loads the saved settings when editing', function () {
    $account = StripeAccount::factory()->create([
        'email_invoices' => true,
        'invoice_sender_name' => 'Clickback',
        'invoice_from_address' => 'invoices@clickback.test',
        'invoice_language' => 'es',
    ]);

    Livewire::test(EditStripeAccount::class, ['record' => $account->id])
        ->assertFormSet([
            'email_invoices' => true,
            'invoice_sender_name' => 'Clickback',
            'invoice_from_address' => 'invoices@clickback.test',
            'invoice_language' => 'es',
        ]);
});
