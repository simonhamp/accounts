<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stripe_transactions', function (Blueprint $table) {
            $table->string('stripe_invoice_id')->nullable()->after('stripe_transaction_id')
                ->comment('Stripe invoice the payment belongs to, when there is one');
            $table->string('stripe_charge_id')->nullable()->after('stripe_invoice_id')
                ->comment('For refunds and disputes: the charge they belong to');
            $table->json('customer_tax_ids')->nullable()->after('customer_address')
                ->comment('Tax IDs from the Stripe invoice, as a list of {type, value}');

            $table->index('stripe_charge_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stripe_transactions', function (Blueprint $table) {
            $table->dropIndex(['stripe_charge_id']);
            $table->dropColumn(['stripe_invoice_id', 'stripe_charge_id', 'customer_tax_ids']);
        });
    }
};
