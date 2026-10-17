<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Stripe importer used to store metadata with an (array) cast on a
 * StripeObject. That dumps the object's protected properties, so each row
 * held keys like "\0*\0_opts" (the request options, with the account's API
 * key in plain text) next to "\0*\0_values" (the real metadata). This keeps
 * the real metadata and drops the rest. Rows already in the right shape are
 * left alone. Nothing here logs or prints a value.
 */
return new class extends Migration
{
    /**
     * PHP's prefix for a protected property name when an object is cast to
     * an array.
     */
    private const PROTECTED_PREFIX = "\0*\0";

    public function up(): void
    {
        DB::table('stripe_transactions')
            ->select(['id', 'metadata'])
            ->whereNotNull('metadata')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $metadata = json_decode($row->metadata, true);

                    if (! is_array($metadata) || ! $this->holdsStripeInternals($metadata)) {
                        continue;
                    }

                    $values = $metadata[self::PROTECTED_PREFIX.'_values'] ?? [];

                    DB::table('stripe_transactions')
                        ->where('id', $row->id)
                        ->update(['metadata' => json_encode(is_array($values) ? $values : [])]);
                }
            });
    }

    /**
     * Nothing to undo: the dropped keys were the SDK's internal state and a
     * copy of the API key, which must not be written back.
     */
    public function down(): void
    {
        //
    }

    /**
     * @param  array<array-key, mixed>  $metadata
     */
    private function holdsStripeInternals(array $metadata): bool
    {
        foreach (array_keys($metadata) as $key) {
            if (str_starts_with((string) $key, self::PROTECTED_PREFIX)) {
                return true;
            }
        }

        return false;
    }
};
