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
        Schema::table('stripe_accounts', function (Blueprint $table) {
            $table->boolean('email_invoices')->default(false)->after('api_key')
                ->comment('Email each invoice made by the daily sync to the customer');
            $table->string('invoice_sender_name')->nullable()->after('email_invoices');
            $table->string('invoice_from_address')->nullable()->after('invoice_sender_name');
            $table->string('invoice_reply_to')->nullable()->after('invoice_from_address');
            $table->string('invoice_bcc')->nullable()->after('invoice_reply_to');
            $table->string('invoice_language', 2)->default('en')->after('invoice_bcc')
                ->comment('Language of the PDF attached to invoice emails: en or es');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stripe_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'email_invoices',
                'invoice_sender_name',
                'invoice_from_address',
                'invoice_reply_to',
                'invoice_bcc',
                'invoice_language',
            ]);
        });
    }
};
