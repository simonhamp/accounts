<?php

use App\Models\StripeTransaction;
use Stripe\StripeObject;

function runScrubLeakedStripeMetadataMigration(): void
{
    (require database_path('migrations/2026_10_17_161956_scrub_leaked_stripe_internals_from_stripe_transactions_metadata.php'))->up();
}

/**
 * Metadata stored the way the old importer stored it: a StripeObject cast to
 * an array, which carries the request options and API key.
 *
 * @param  array<string, string>  $values
 * @return array<string, mixed>
 */
function leakedStripeMetadata(array $values): array
{
    return (array) StripeObject::constructFrom($values, ['api_key' => 'sk_live_fake']);
}

it('drops the Stripe internals and keeps the real metadata', function () {
    $transaction = StripeTransaction::factory()->create([
        'metadata' => leakedStripeMetadata(['clickback_advertiser_id' => '42']),
    ]);

    expect($transaction->getRawOriginal('metadata'))
        ->toContain('sk_live_fake')
        ->toContain('_opts');

    runScrubLeakedStripeMetadataMigration();

    $transaction->refresh();

    expect($transaction->metadata)->toBe(['clickback_advertiser_id' => '42'])
        ->and($transaction->getRawOriginal('metadata'))
        ->not->toContain('sk_live_fake')
        ->not->toContain('_opts')
        ->not->toContain('apiKey')
        ->not->toContain('\u0000');
});

it('leaves an empty array when the leaked metadata had no values', function () {
    $transaction = StripeTransaction::factory()->create([
        'metadata' => leakedStripeMetadata([]),
    ]);

    runScrubLeakedStripeMetadataMigration();

    expect($transaction->fresh()->metadata)->toBe([])
        ->and($transaction->fresh()->getRawOriginal('metadata'))->not->toContain('sk_live_fake');
});

it('leaves an empty array when the leaked metadata has no values key at all', function () {
    $transaction = StripeTransaction::factory()->create([
        'metadata' => ["\0*\0_opts" => ['apiKey' => 'sk_live_fake']],
    ]);

    runScrubLeakedStripeMetadataMigration();

    expect($transaction->fresh()->metadata)->toBe([])
        ->and($transaction->fresh()->getRawOriginal('metadata'))->not->toContain('sk_live_fake');
});

it('leaves rows that are already clean untouched', function (?array $metadata) {
    $transaction = StripeTransaction::factory()->create(['metadata' => $metadata]);
    $raw = $transaction->fresh()->getRawOriginal('metadata');
    $updatedAt = $transaction->fresh()->updated_at;

    $this->travel(1)->hour();
    runScrubLeakedStripeMetadataMigration();

    $transaction->refresh();

    expect($transaction->getRawOriginal('metadata'))->toBe($raw)
        ->and($transaction->metadata)->toBe($metadata)
        ->and($transaction->updated_at->equalTo($updatedAt))->toBeTrue();
})->with([
    'charge metadata' => [['clickback_advertiser_id' => '42']],
    'dispute metadata' => [['status' => 'needs_response', 'reason' => 'fraudulent']],
    'empty' => [[]],
    'null' => [null],
]);
