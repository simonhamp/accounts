<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice email settings first lived on stripe_accounts, added by a
 * migration earlier on this branch that has since been removed. They now
 * live on brands. Only databases that ran that earlier migration have the
 * columns, so each one is dropped only when it is there. Nothing had been
 * deployed, so there is no data to carry over.
 */
return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private const COLUMNS = [
        'email_invoices',
        'invoice_sender_name',
        'invoice_from_address',
        'invoice_reply_to',
        'invoice_bcc',
        'invoice_language',
    ];

    public function up(): void
    {
        $present = array_values(array_filter(
            self::COLUMNS,
            fn (string $column) => Schema::hasColumn('stripe_accounts', $column),
        ));

        if ($present === []) {
            return;
        }

        Schema::table('stripe_accounts', function (Blueprint $table) use ($present) {
            $table->dropColumn($present);
        });
    }

    /**
     * Nothing to restore: the settings belong to brands now.
     */
    public function down(): void
    {
        //
    }
};
