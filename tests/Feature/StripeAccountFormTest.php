<?php

use App\Filament\Resources\StripeAccounts\Pages\CreateStripeAccount;
use App\Filament\Resources\StripeAccounts\Pages\EditStripeAccount;
use App\Models\Brand;
use App\Models\Person;
use App\Models\StripeAccount;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

it('picks the brand instead of holding invoice email settings', function () {
    Livewire::test(CreateStripeAccount::class)
        ->assertFormFieldExists('brand_id')
        ->assertFormFieldDoesNotExist('email_invoices')
        ->assertFormFieldDoesNotExist('invoice_from_address')
        ->assertFormFieldDoesNotExist('invoice_language');
});

it('saves the brand on a new account', function () {
    $person = Person::factory()->create();
    $brand = Brand::factory()->create(['name' => 'Clickback']);

    Livewire::test(CreateStripeAccount::class)
        ->fillForm([
            'person_id' => $person->id,
            'brand_id' => $brand->id,
            'account_name' => 'Clickback',
            'api_key' => 'rk_test_123',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $account = StripeAccount::firstWhere('account_name', 'Clickback');

    expect($account->brand->is($brand))->toBeTrue()
        ->and($account->api_key)->toBe('rk_test_123');
});

it('can leave an account without a brand', function () {
    $account = StripeAccount::factory()->create(['brand_id' => Brand::factory()->create()->id]);

    Livewire::test(EditStripeAccount::class, ['record' => $account->id])
        ->assertFormSet(['brand_id' => $account->brand_id])
        ->fillForm(['brand_id' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($account->fresh()->brand_id)->toBeNull();
});
