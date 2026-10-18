<?php

use App\Models\StripeAccount;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

function runDropStripeAccountEmailSettingsMigration(): void
{
    (require database_path('migrations/2026_10_18_180756_drop_invoice_email_settings_from_stripe_accounts_table.php'))->up();
}

/**
 * @var array<int, string>
 */
const OLD_STRIPE_ACCOUNT_EMAIL_COLUMNS = [
    'email_invoices',
    'invoice_sender_name',
    'invoice_from_address',
    'invoice_reply_to',
    'invoice_bcc',
    'invoice_language',
];

it('drops the old invoice email columns from a database that has them', function () {
    Schema::table('stripe_accounts', function (Blueprint $table) {
        $table->boolean('email_invoices')->default(false);
        $table->string('invoice_sender_name')->nullable();
        $table->string('invoice_from_address')->nullable();
        $table->string('invoice_reply_to')->nullable();
        $table->string('invoice_bcc')->nullable();
        $table->string('invoice_language', 2)->default('en');
    });
    $account = StripeAccount::factory()->create(['account_name' => 'Laradevs']);

    expect(Schema::hasColumns('stripe_accounts', OLD_STRIPE_ACCOUNT_EMAIL_COLUMNS))->toBeTrue();

    runDropStripeAccountEmailSettingsMigration();

    foreach (OLD_STRIPE_ACCOUNT_EMAIL_COLUMNS as $column) {
        expect(Schema::hasColumn('stripe_accounts', $column))->toBeFalse();
    }

    expect(Schema::hasColumn('stripe_accounts', 'brand_id'))->toBeTrue()
        ->and($account->fresh()->account_name)->toBe('Laradevs');
});

it('does nothing on a database that never had them', function () {
    $account = StripeAccount::factory()->create(['account_name' => 'Estilistas']);
    $columns = Schema::getColumnListing('stripe_accounts');

    runDropStripeAccountEmailSettingsMigration();

    expect(Schema::getColumnListing('stripe_accounts'))->toBe($columns)
        ->and($account->fresh()->account_name)->toBe('Estilistas');
});
