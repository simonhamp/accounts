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
        Schema::table('other_incomes', function (Blueprint $table) {
            $table->foreignId('stripe_transaction_id')
                ->nullable()
                ->after('income_source_id')
                ->constrained('stripe_transactions')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('other_incomes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stripe_transaction_id');
        });
    }
};
