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
        Schema::create('stripe_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stripe_account_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_transaction_id')->unique();
            $table->enum('type', ['payment', 'refund', 'chargeback', 'fee']);
            $table->integer('amount')->comment('Amount in cents');
            $table->string('currency', 3);
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->text('customer_address')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->enum('status', ['pending_review', 'ready', 'invoiced'])->default('pending_review');
            $table->boolean('is_complete')->default(false)->comment('All required details present');
            $table->timestamp('transaction_date');
            $table->timestamps();

            $table->index(['stripe_account_id', 'status']);
            $table->index('transaction_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stripe_transactions');
    }
};
