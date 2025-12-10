<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For SQLite, we need to recreate the table to modify the enum constraint
        // Change status from enum to string to allow more flexibility
        if (DB::getDriverName() === 'sqlite') {
            // SQLite requires table recreation for column type changes
            // Create a new table, copy data, drop old, rename new
            DB::statement('PRAGMA foreign_keys=off');

            DB::statement('CREATE TABLE stripe_transactions_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                stripe_account_id INTEGER NOT NULL,
                stripe_transaction_id VARCHAR(255) NOT NULL UNIQUE,
                type VARCHAR(255) NOT NULL CHECK(type IN (\'payment\', \'refund\', \'chargeback\', \'fee\')),
                amount INTEGER NOT NULL,
                currency VARCHAR(3) NOT NULL,
                customer_name VARCHAR(255),
                customer_email VARCHAR(255),
                customer_address TEXT,
                description TEXT,
                metadata TEXT,
                status VARCHAR(255) NOT NULL DEFAULT \'pending_review\' CHECK(status IN (\'pending_review\', \'ready\', \'ignored\')),
                transaction_date DATETIME NOT NULL,
                created_at DATETIME,
                updated_at DATETIME,
                FOREIGN KEY (stripe_account_id) REFERENCES stripe_accounts(id) ON DELETE CASCADE
            )');

            DB::statement('INSERT INTO stripe_transactions_new SELECT * FROM stripe_transactions');
            DB::statement('DROP TABLE stripe_transactions');
            DB::statement('ALTER TABLE stripe_transactions_new RENAME TO stripe_transactions');

            DB::statement('CREATE INDEX stripe_transactions_stripe_account_id_status_index ON stripe_transactions (stripe_account_id, status)');
            DB::statement('CREATE INDEX stripe_transactions_transaction_date_index ON stripe_transactions (transaction_date)');

            DB::statement('PRAGMA foreign_keys=on');
        } else {
            // For MySQL/PostgreSQL, we can modify the enum directly
            Schema::table('stripe_transactions', function (Blueprint $table) {
                $table->enum('status', ['pending_review', 'ready', 'ignored'])
                    ->default('pending_review')
                    ->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=off');

            DB::statement('CREATE TABLE stripe_transactions_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                stripe_account_id INTEGER NOT NULL,
                stripe_transaction_id VARCHAR(255) NOT NULL UNIQUE,
                type VARCHAR(255) NOT NULL CHECK(type IN (\'payment\', \'refund\', \'chargeback\', \'fee\')),
                amount INTEGER NOT NULL,
                currency VARCHAR(3) NOT NULL,
                customer_name VARCHAR(255),
                customer_email VARCHAR(255),
                customer_address TEXT,
                description TEXT,
                metadata TEXT,
                status VARCHAR(255) NOT NULL DEFAULT \'pending_review\' CHECK(status IN (\'pending_review\', \'ready\', \'invoiced\')),
                transaction_date DATETIME NOT NULL,
                created_at DATETIME,
                updated_at DATETIME,
                FOREIGN KEY (stripe_account_id) REFERENCES stripe_accounts(id) ON DELETE CASCADE
            )');

            // Convert 'ignored' back to 'pending_review' before migration
            DB::table('stripe_transactions')
                ->where('status', 'ignored')
                ->update(['status' => 'pending_review']);

            DB::statement('INSERT INTO stripe_transactions_new SELECT * FROM stripe_transactions');
            DB::statement('DROP TABLE stripe_transactions');
            DB::statement('ALTER TABLE stripe_transactions_new RENAME TO stripe_transactions');

            DB::statement('CREATE INDEX stripe_transactions_stripe_account_id_status_index ON stripe_transactions (stripe_account_id, status)');
            DB::statement('CREATE INDEX stripe_transactions_transaction_date_index ON stripe_transactions (transaction_date)');

            DB::statement('PRAGMA foreign_keys=on');
        } else {
            Schema::table('stripe_transactions', function (Blueprint $table) {
                $table->enum('status', ['pending_review', 'ready', 'invoiced'])
                    ->default('pending_review')
                    ->change();
            });
        }
    }
};
