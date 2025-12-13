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
        Schema::create('database_backups', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('path');
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('invoices_count')->default(0);
            $table->unsignedInteger('bills_count')->default(0);
            $table->unsignedInteger('stripe_transactions_count')->default(0);
            $table->unsignedInteger('other_incomes_count')->default(0);
            $table->unsignedInteger('people_count')->default(0);
            $table->unsignedInteger('bank_accounts_count')->default(0);
            $table->timestamps();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('database_backups');
    }
};
